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
				`refresh_token` VARCHAR(255) NOT NULL
			PRIMARY KEY (`user_id`,`system`)
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



		protected function repairEntities($value) {
			return preg_replace('/&(?!(#[0-9]+|#x[0-9a-f]+|amp|lt|gt|quot);)/i', '&amp;', trim($value));
		}

		private function refreshToken() {
			$refreshToken = 'W1JmxG-Cogm-4aSc5Vlen37XaQTj74aQcQiTtXax5UgY7M_AJ--kLX8xNVk8LtCpmueFfcYlRK6UgQLJ-XHsxpw6kZzPpKKccRQeC4z2ldTMfXdIWajZ6CHuebs';

			try {

				$tokenInfo = new OpenIdTokeninfo();
				$tokenInfo = $tokenInfo->createFromRefreshToken(array('refresh_token' => $refreshToken), $apiContext);

				$params = array('access_token' => $tokenInfo->getAccessToken());
				$userInfo = OpenIdUserinfo::getUserinfo($params, $apiContext);

			} catch (Exception $ex) {
				ResultPrinter::printError("User Information", "User Info", null, $params, $ex);
				exit(1);
			}
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

				$baseUrl = 'http://jci.dev/maze/extension/paypal/payment';

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

				$baseUrl = 'http://jci.dev/maze/extension/paypal/consent';
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

				$scriptInclude = '<span id="lippButton"></span>
					<script src="https://www.paypalobjects.com/js/external/api.js"></script>
					<script>
					paypal.use( ["login"], function(login) {
					  login.render ({
						"appid": "'.$this->clientId.'",' .
						($this->mode == 'sandbox') ? '"authend": "sandbox",' : '' .
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
