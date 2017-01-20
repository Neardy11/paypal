<?php

	
    // Include autoloader:
    // require_once DOCROOT . '/vendor/autoload.php';

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.entrymanager.php');

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

	$token = MySQL::cleanValue($_GET['token']);
    $agreement = new \PayPal\Api\Agreement();
    try {
        // ## Execute Agreement
        // Execute the agreement by passing in the token
        $agreement->execute($token, $paypalExtension->getApiContext());

        $agreementEntryId = Symphony::Database()->fetchVar('entry_id',0,"SELECT entry_id FROM tbl_paypal_agreement_token WHERE token = '{$token}';");

        $agreementEntry = current(EntryManager::fetch($agreementEntryId));

        $dataToUpdate = array(
        		'status' => $agreement->getState(),
        		'agreement-id' => $agreement->getId(),
        		'expiry' => '+30 days',
        	);

        $agreementEntry->setDataFromPost($dataToUpdate,$errors,false,true);
        $agreementEntry->commit();

		$select = "SELECT `date` FROM tbl_bf_tradertip WHERE `period` = 'daily' AND `instrument` = 'EUR-USD' ORDER BY tradertipid DESC LIMIT 1";

		$date = Symphony::Database()->fetchVar('date',0,$select);

		$redirectUrl = URL . '/technical-analysis/trader-tip/EUR-USD/' . $date . '/?subscribed';
			
        header('Location: ' . $redirectUrl, true, 302);
        exit;


        //redirect to success page / tradertip page?
        var_dump($agreement);die;
    } catch (Exception $ex) {
        // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY


		var_dump($agreement);
		var_dump($ex);die('error');
		return "error";
        // ResultPrinter::printError("Executed an Agreement", "Agreement", $agreement->getId(), $_GET['token'], $ex);
        exit(1);
    }



	die;
} else {
	echo 'user did not approve payment';
}