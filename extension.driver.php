<?php


	require_once(EXTENSIONS . '/paypal/vendor/autoload.php');
	
	use PayPal\Auth\OAuthTokenCredential;
	use PayPal\Rest\ApiContext;
	use PayPal\Api\OpenIdSession;
	use PayPal\Api\OpenIdTokeninfo;
	use PayPal\Api\OpenIdUserinfo;
	use PayPal\Api\Amount;
	use PayPal\Api\Details;
	use PayPal\Api\Item;
	use PayPal\Api\ItemList;
	use PayPal\Api\Payer;
	use PayPal\Api\Payment;
	use PayPal\Api\RedirectUrls;
	use PayPal\Api\Transaction;

	//
	use PayPal\Api\ChargeModel;
	use PayPal\Api\Currency;
	use PayPal\Api\MerchantPreferences;
	use PayPal\Api\PaymentDefinition;
	use PayPal\Api\Plan;
	use PayPal\Api\Patch;
	use PayPal\Api\PatchRequest;
	use PayPal\Common\PayPalModel;
	use PayPal\Api\Agreement;

	Class extension_Paypal extends Extension{

		private $apiContext;
		private $clientId;
		private $clientSecret;
		private $currency = 'EUR';

		public function __construct() {
			// die('construct');
			$this->clientId = Symphony::Configuration()->get('client-id','paypal');
			$this->clientSecret = Symphony::Configuration()->get('client-secret','paypal');
			$this->mode = Symphony::Configuration()->get('mode','paypal');
			$this->apiContext = $this->generateApiContext($this->clientId, $this->clientSecret);

			$this->plans = Symphony::Configuration()->get('plans','paypal');
			$this->agreements = Symphony::Configuration()->get('agreements','paypal');
		}

		public function getApiContext(){
			return $this->apiContext;
		}

		/**
		 * Helper method for generating an APIContext for all calls
		 * @param string $clientId Client ID
		 * @param string $clientSecret Client Secret
		 * @return PayPal\Rest\ApiContext
		 */
		private function generateApiContext($clientId, $clientSecret) {
			// #### SDK configuration
			// Register the sdk_config.ini file in current directory
			// as the configuration source.
			/*
			if(!defined("PP_CONFIG_PATH")) {
				define("PP_CONFIG_PATH", __DIR__);
			}
			*/
			// ### Api context
			// Use an ApiContext object to authenticate
			// API calls. The clientId and clientSecret for the
			// OAuthTokenCredential class can be retrieved from
			// developer.paypal.com
			$apiContext = new ApiContext(
				new OAuthTokenCredential(
					$clientId,
					$clientSecret
				)
			);
			// Comment this line out and uncomment the PP_CONFIG_PATH
			// 'define' block if you want to use static file
			// based configuration
			$apiContext->setConfig(
				array(
					'mode' => $this->mode,
					'log.LogEnabled' => true,
					'log.FileName' => MANIFEST . '/logs/PayPal.log',
					'cache.FileName' => MANIFEST . '/cache/PayPal',
					'log.LogLevel' => 'DEBUG', // PLEASE USE `FINE` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
					'validation.level' => 'log',
					'cache.enabled' => true,
					// 'http.CURLOPT_CONNECTTIMEOUT' => 30
					// 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
				)
			);
			// Partner Attribution Id
			// Use this header if you are a PayPal partner. Specify a unique BN Code to receive revenue attribution.
			// To learn more or to request a BN Code, contact your Partner Manager or visit the PayPal Partner Portal
			// $apiContext->addRequestHeader('PayPal-Partner-Attribution-Id', '123123123');
			return $apiContext;
		}
		
		/**
		 * Installation
		 */
		public function install() {
			// A table to keep track of user tokens in relation to the current current user id
			Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_paypal_token` (
				`user_id` VARCHAR(255) NOT NULL ,
				`refresh_token` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`user_id`)
			)ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

			Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_paypal_agreement_token` (
				`entry_id` VARCHAR(255) NOT NULL ,
				`token` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`entry_id`)
			)ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
			
			return true;
		}
		
		/**
		 * Update
		 */
		public function update() {
			$this->install();
		}

		
		public function getSubscribedDelegates() {
			return array(
				// array(
				// 	'page' => '/system/preferences/',
				// 	'delegate' => 'AddCustomPreferenceFieldsets',
				// 	'callback' => 'appendPreferences'
				// ),
				// array(
				// 	'page' => '/system/preferences/',
				// 	'delegate' => 'Save',
				// 	'callback' => 'savePreferences'
				// ),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendProcessEvents',
					'callback' => 'appendEventXML'
				),
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'entryPostEdit'
				),
				array(
					'page'		=> '/publish/create/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'entryPostEdit'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'createAgreement'
				),
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				// array(
				// 	'page' => '/frontend/',
				// 	'delegate' => 'FrontendParamsResolve',
				// 	'callback' => 'appendAccessToken'
				// ),
				// array(
				// 	'page' => '/frontend/',
				// 	'delegate' => 'FrontendPageResolved',
				// 	'callback' => 'frontendPageResolved'
				// ),
			);
		}


		public function entryPostEdit($context){

			//return if article not published or future dated.
			$entry = $context['entry'];

			$sectionID = $context['entry']->get('section_id');

			if ($sectionID != $this->plans['section_id']){
				return;
			}


			$paypalPlanId = $entry->getData($this->plans['plan-id'])['value'];

			$name = $entry->getData($this->plans['name'])['value'];
			$description = $entry->getData($this->plans['description'])['value'];
			$price = $entry->getData($this->plans['price'])['value'];
			$currency = $entry->getData($this->plans['currency'])['value'];
			$type = $entry->getData($this->plans['Type'])['value'];
			$type = 'REGULAR';

			if (empty($paypalPlanId)){


				// Create a new instance of Plan object
				$plan = new Plan();
				// # Basic Information
				// Fill up the basic information that is required for the plan
				$plan->setName($name)
					->setDescription($description)
					->setType('infinite');
				// # Payment definitions for this billing plan.
				$paymentDefinition = new PaymentDefinition();
				// The possible values for such setters are mentioned in the setter method documentation.
				// Just open the class file. e.g. lib/PayPal/Api/PaymentDefinition.php and look for setFrequency method.
				// You should be able to see the acceptable values in the comments.
				$paymentDefinition->setName('Regular Payment Definition')
					->setType($type)
					->setFrequency('MONTH')
					->setFrequencyInterval("1")
					->setCycles("0")
					->setAmount(new Currency(array('value' => $price, 'currency' => $currency)));

				$plan->setPaymentDefinitions(array($paymentDefinition));


				// Charge Models
				/*$chargeModel = new ChargeModel();
				$chargeModel->setType('SHIPPING')
					->setAmount(new Currency(array('value' => 10, 'currency' => 'USD')));
				$paymentDefinition->setChargeModels(array($chargeModel));*/

				$merchantPreferences = new MerchantPreferences();
				$baseUrl = $baseUrl = SYMPHONY_URL . '/extension/paypal/agreement';;
				// ReturnURL and CancelURL are not required and used when creating billing agreement with payment_method as "credit_card".
				// However, it is generally a good idea to set these values, in case you plan to create billing agreements which accepts "paypal" as payment_method.
				// This will keep your plan compatible with both the possible scenarios on how it is being used in agreement.
				$merchantPreferences->setReturnUrl("$baseUrl?success=true")
					->setCancelUrl("$baseUrl?success=false")
					->setAutoBillAmount("yes")
					->setInitialFailAmountAction("CANCEL") // CONTINUE 
					->setMaxFailAttempts("0")
					// REQUIRED setup fee to take payment on 1st month - here is where a first-month discount can be provided
					->setSetupFee(new Currency(array('value' => $price, 'currency' => $currency)));
				$plan->setMerchantPreferences($merchantPreferences);


				$request = clone $plan;
				try {
					$output = $plan->create($this->apiContext);

					// save id into database
					$entry->setData($this->plans['plan-id'], array('value' => $output->getId()));
					$entry->commit();

				} catch (Exception $ex) {

					// var_dump($request);
					var_dump($ex);die('error');
					return "error";
					//log the error
					// echo("Error Creating Payment Using PayPal.", "Payment", null, $request, $ex);
					// exit(1);
				}

			} else {
				$plan = Plan::get($paypalPlanId, $this->apiContext);

				$patch = new Patch();
				$value = new PayPalModel('{
					   "state":"ACTIVE"
					 }');
				$patch->setOp('replace')
					->setPath('/')
					->setValue($value);
				$patchRequest = new PatchRequest();
				$patchRequest->addPatch($patch);

				$plan->update($patchRequest, $this->apiContext);

				$plan = Plan::get($plan->getId(), $this->apiContext);

			}
			
		}


		/**
		 * The Members extension provides a number of filters for users to add their
		 * events to do various functionality. This negates the need for custom events
		 *
		 * @uses AppendEventFilter
		 *
		 * @param $context
		 */
		public function appendFilter($context) {
			$selected = !is_array($context['selected']) ? array() : $context['selected'];

			// Add Payment
			$context['options'][] = array(
				'create-paypal-agreement',
				in_array('create-paypal-agreement', $selected),
				__('Create Paypal Agreement')
			);
		}

		public function createAgreement($context){

			if (in_array('create-paypal-agreement',$context['event']->eParamFILTERS)) {

				// $this->plans['section_id'];

				// get plan id
				$agreementEntry = $context['entry'];
				$planEntryId = $agreementEntry->getData($this->agreements['plan'])['relation_id'];

				$planEntry = current(EntryManager::fetch($planEntryId));
				$paypalPlanId = $planEntry->getData($this->plans['plan-id'])['value'];
				$name = $planEntry->getData($this->plans['name'])['value'];
				$description = $planEntry->getData($this->plans['description'])['value'];

				if (empty($paypalPlanId)){
					return;
				}

				$agreement = new Agreement();
				$agreement->setName($name)
					->setDescription($description)
					->setStartDate(date("Y-m-d\TH:i:s\Z",strtotime('+1 month')));
				// Add Plan ID
				// Please note that the plan Id should be only set in this case.
				$plan = new Plan();
				$plan->setId($paypalPlanId);
				$agreement->setPlan($plan);
				// Add Payer
				$payer = new Payer();
				$payer->setPaymentMethod('paypal');
				$agreement->setPayer($payer);

				try {
					// Please note that as the agreement has not yet activated, we wont be receiving the ID just yet.
					$agreement = $agreement->create($this->apiContext);
					// ### Get redirect url
					// The API response provides the url that you must redirect
					// the buyer to. Retrieve the url from the $agreement->getApprovalLink()
					// method
					$approvalUrl = $agreement->getApprovalLink();
					$approvalLink = new XMLElement('approval-link',General::sanitize($approvalUrl));

					$token = substr($approvalUrl, strpos($approvalUrl, 'token=') + strlen('token='));

					Symphony::Database()->insert(
						array(
								'token' => $token,
								'entry_id' => $agreementEntry->get('id'),
							)
						,'tbl_paypal_agreement_token');

/*
					var_dump($token);
					var_dump($approvalUrl);die;
*/
					// add data if necessary to XML Output
					$context['messages'] = array(
						array(
							'create-paypal-agreement',
							'passed',
							$approvalLink,
							// $transactionData
						)
					);
				
					if ($_POST['redirect']){
						header('Location: ' . $approvalUrl, true, 302);
						exit;
					}


				} catch (Exception $ex) {


					// add data if necessary to XML Output
					$context['messages'] = array(
						array(
							'create-paypal-agreement',
							'failed',
						)
					);

					var_dump($request);
					var_dump($ex);die('error');
					return "error";
				}
			}

		}

		protected function repairEntities($value) {
			return preg_replace('/&(?!(#[0-9]+|#x[0-9a-f]+|amp|lt|gt|quot);)/i', '&amp;', trim($value));
		}

		private function paypalItemFromArray(array $itemDetails){
			$item = new Item();
			// $item->setCurrency($this->currency);

			foreach ($itemDetails as $key => $value) {
				$funciton = 'set' . ucfirst($key);
				$item->$funciton($value);
			}

			return $item;
		}

		public function pay(array $items = null, $invoiceNo = null) {

			if (!isset($invoiceNo)){
				$invoiceNo = uniqid();
			}

			if (isset($items) && !empty($items)){

				$baseUrl = SYMPHONY_URL . '/extension/paypal/payment';

				$payer = new Payer();
				$payer->setPaymentMethod("paypal");

				$itemList = new ItemList();

				$subTotal = 0;
				$taxTotal = 0;
				$shipping = 0;

				foreach ($items as $key => $itemDetails) {
					$subTotal += $itemDetails['price'];
					if (isset($itemDetails['tax'])){
						$taxTotal += $itemDetails['tax'];
					}
					$itemList->addItem($this->paypalItemFromArray($itemDetails));
				}

				$details = new Details();
				$details->setShipping($shipping)
					->setTax($taxTotal)
					->setSubtotal($subTotal);

				$amount = new Amount();
				$amount->setCurrency($this->currency)
					->setTotal($subTotal + $taxTotal)
					->setDetails($details);

				$transaction = new Transaction();
				$transaction->setAmount($amount)
					->setItemList($itemList)
					->setDescription("JCI Malta Membership")
					->setInvoiceNumber($invoiceNo);

				$redirectUrls = new RedirectUrls();
				$redirectUrls->setReturnUrl("$baseUrl?success=true")
					->setCancelUrl("$baseUrl?success=false");

				$payment = new Payment();
				$payment->setIntent("sale")
					->setPayer($payer)
					->setRedirectUrls($redirectUrls)
					->setTransactions(array($transaction));

				$request = clone $payment;
				try {
					$payment->create($this->apiContext);
				} catch (Exception $ex) {

					var_dump($request);
					var_dump($ex);die;
					return "error";
					//log the error
					// echo("Error Creating Payment Using PayPal.", "Payment", null, $request, $ex);
					// exit(1);
				}

				$return = array(
					'id' => $payment->getId(), 
					'link' =>$payment->getApprovalLink() 
				);

				return $return;

			}

		}

		public function appendEventXML(array $context = null) {
			$result = new XMLElement('paypal');

			$cookie = new Cookie('paypal',TWO_WEEKS, __SYM_COOKIE_PATH__, null, true);
			$accessToken = $cookie->get('token');

			//$accessToken->getRefreshToken()

			// var_dump($accessToken);die;

			if (!empty($accessToken)){
				
				$user = $cookie->get('user');

				$result->appendChild(General::array_to_xml($result,$user));

			} else {

				$baseUrl = SYMPHONY_URL . '/extension/paypal/consent';
				// ### Get User Consent URL
				// The clientId is stored in the bootstrap file
				//Get Authorization URL returns the redirect URL that could be used to get user's consent
				$redirectUrl = OpenIdSession::getAuthorizationUrl(
					$baseUrl,
					array('openid', 'profile', 'address', 'email', 'phone',
						'https://uri.paypal.com/services/paypalattributes', 'https://uri.paypal.com/services/expresscheckout'),
					null,
					null,
					null,
					$this->apiContext
				);

				$authend = ($this->mode == 'sandbox') ? '"authend": "sandbox",' : '';

				$scriptInclude = '<span id="lippButton"></span>
					<script src="https://www.paypalobjects.com/js/external/api.js"></script>
					<script>
					paypal.use( ["login"], function(login) {
					  login.render ({
						"appid": "'.$this->clientId.'",' .
						$authend .
						'"scopes": "openid profile email address phone https://uri.paypal.com/services/paypalattributes https://uri.paypal.com/services/expresscheckout",
						"containerid": "lippButton",
						"locale": "en-us",
						"returnurl": "'.$baseUrl.'"
					  });
					});
					</script>';

				$result->appendChild( new XMLElement('authorize',$this->repairEntities($scriptInclude),array('url'=>$this->repairEntities($redirectUrl))));
				// var_dump($redirectUrl);die;
			}
			
			$context['wrapper']->appendChild($result);
		}

	}
