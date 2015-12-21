<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	$paypalExtension = ExtensionManager::create('paypal');

use PayPal\Api\OpenIdTokeninfo;
use PayPal\Api\OpenIdUserinfo;
use PayPal\Exception\PayPalConnectionException;
// session_start();
// ### User Consent Response
// PayPal would redirect the user to the redirect_uri mentioned when creating the consent URL.
// The user would then able to retrieve the access token by getting the code, which is returned as a GET parameter.
if (isset($_GET['code'])) {
	$code = $_GET['code'];
	try {
		// Obtain Authorization Code from Code, Client ID and Client Secret
		$accessToken = OpenIdTokeninfo::createFromAuthorizationCode(array('code' => $code), null, null, $paypalExtension->getApiContext());
	} catch (PayPalConnectionException $ex) {
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
	// ResultPrinter::printError("Obtained Access Token", "Access Token", null, $_GET['code'], $ex);
		var_dump($ex);
		exit(1);
	}

	$cookie = new Cookie('paypal',TWO_WEEKS, __SYM_COOKIE_PATH__, null, true);
	$cookie->set('token',$accessToken->getAccessToken());
	$cookie->set('refresh-token',$accessToken->getRefreshToken());

	
	$params = array('access_token' => $accessToken->getAccessToken());
	$userInfo = OpenIdUserinfo::getUserinfo($params, $paypalExtension->getApiContext());

	$userAddress = $userInfo->getAddress();

	$address = array(
			'street' => $userAddress->getStreetAddress(),
			'locality' => $userAddress->getLocality(),
			'region' => $userAddress->getRegion(),
			'country' => $userAddress->getCountry(),
			'postal-code' => $userAddress->getPostalCode()
		);

	$user = array(
			'id' => $userInfo->getUserId(),
			'name' => $userInfo->getGivenName(),
			'surname' => $userInfo->getFamilyName(),
			'email' => $userInfo->getEmail(),
			'date-of-birth' => $userInfo->getBirthday(),
			'mobile' => $userInfo->getPhoneNumber(),
			'address' => $address
		);

	$cookie->set('user',$user);
	$cookie->set('address',$address);

	//TODO Consider if it's worth inserting the data into the database at this stage / updating existing records

	// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
 // ResultPrinter::printResult("Obtained Access Token", "Access Token", $accessToken->getAccessToken(), $_GET['code'], $accessToken);
}
echo "<script>
	window.opener.location.reload();
	window.close();
</script>";die;

	Class contentExtensionPaypalConsent extends AdministrationPage {

		private $articleSections = array();
		private $articleSectionHandle = array();
		private $articleSubsections = array();

		private $weekday = array(
			'0'=>'Sunday',
			'1'=>'Monday',
			'2'=>'Tuesday',
			'3'=>'Wednesday',
			'4'=>'Thursday',
			'5'=>'Friday',
			'6'=>'Saturday',
		);
			
		private $fields = array();

		function __construct(){
			parent::__construct();
		}


		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Export Articles'))));

			$this->Form->appendChild($tableActions);
		}

	}
