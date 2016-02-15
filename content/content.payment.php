<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	$paypalExtension = ExtensionManager::create('paypal');
	
	use PayPal\Api\Amount;
	use PayPal\Api\Details;
	use PayPal\Api\ExecutePayment;
	use PayPal\Api\Payment;
	use PayPal\Api\PaymentExecution;
	use PayPal\Api\Transaction;

//login a member if available for authentication purposes
$this->_context['member-login']='yes';



if (isset($_GET['success']) && $_GET['success'] == 'true') {

	$paymentId = $_GET['paymentId'];
	$payment = Payment::get($paymentId, $paypalExtension->getApiContext());

	$execution = new PaymentExecution();
	$execution->setPayerId($_GET['PayerID']);

	try {
		$result = $payment->execute($execution, $paypalExtension->getApiContext());

		try {
			$payment = Payment::get($paymentId, $paypalExtension->getApiContext());

			$state = $payment->getState();

			$transaction = current($payment->getTransactions());

			$invoiceID = $transaction->getInvoiceNumber();

			$invoice = current(EntryManager::fetch($invoiceID));

			$sectionID = $invoice->get('section_id');
			$fieldID = FieldManager::fetchFieldIDFromElementName('status',$sectionID);

			$invoice->setData($fieldID,array('value'=>$state,'handle'=>General::createHandle($state)));
			$invoice->commit();


			$itemFieldID = FieldManager::fetchFieldIDFromElementName('item',$sectionID);
			if (in_array("JCI Malta Membership", $invoice->getData($itemFieldID)['description'])){
				//user paid for a membership kindly convert user to a member
				$memberFieldID = FieldManager::fetchFieldIDFromElementName('member',$sectionID);
				$memberID = $invoice->getData($memberFieldID)['relation_id'];

				$member = current(EntryManager::fetch($memberID));
				$roleFieldID = FieldManager::fetchFieldIDFromElementName('role',$member->get('section_id'));
				$member->setData($roleFieldID,array('role_id'=>2));
				$member->commit();

				$emailID = FieldManager::fetchFieldIDFromElementName('email',$member->get('section_id'));
				$email = $member->getData($emailID)['value'];

				$member = ExtensionManager::getInstance('members')->getMemberDriver()->login(array('email'=>$email));
			}

            header('Location: ' . URL . '/register/?thankyou=1', true, 302);
            exit;
			var_dump($invoice->getData($itemFieldID)['description']);

			// if item contains membership change the role of the user to a member.

			echo($state);
		} catch (Exception $ex) {
			//getting payment
			var_dump($ex);die;
		}
	} catch (Exception $ex) {
		//executing payment
		var_dump($ex);die;
	}



	die;
} else {
	echo 'user did not approve payment';
}