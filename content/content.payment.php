<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	$paypalExtension = ExtensionManager::create('paypal');
	
	use PayPal\Api\Amount;
	use PayPal\Api\Details;
	use PayPal\Api\ExecutePayment;
	use PayPal\Api\Payment;
	use PayPal\Api\PaymentExecution;
	use PayPal\Api\Transaction;

if (isset($_GET['success']) && $_GET['success'] == 'true') {

	$paymentId = $_GET['paymentId'];
	$payment = Payment::get($paymentId, $paypalExtension->getApiContext());

	$execution = new PaymentExecution();
	$execution->setPayerId($_GET['PayerID']);

	try {
		// $result = $payment->execute($execution, $paypalExtension->getApiContext());

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