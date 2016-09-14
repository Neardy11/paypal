<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');
	require_once(EXTENSIONS . '/paypal/vendor/autoload.php');
	use PayPal\Api\CreditCard;

	Class eventPaypal_save_card extends Event {

		const ROOTELEMENT = 'paypal-save-card';

		public static function about(){
			return array(
				'name' => 'Paypal: Save Card',
				'author' => array(
					'name' => 'Jonathan Mifsud',
					'website' => 'http://jonmifsud.com',
					'email' => 'info@jonmifsud.com'),
				'version' => 'Shopping Cart 1.0',
				'release-date' => '2014-11-12'
			);
		}

		public function load(){
			//just trigger it
			// return $this->__trigger();
			if(isset($_POST['action']['paypal-save-card'])) return $this->__trigger();
		}

		public static function documentation(){
			// Fetch all the Email Templates available and add to the end of the documentation
			$templates = extension_Members::fetchEmailTemplates();
			$div = new XMLElement('div');

			if(!empty($templates)) {
				// Template
				$label = new XMLElement('label', __('Order Confirmation Email Template'));
				$activate_account_templates = extension_Members::setActiveTemplate($templates, 'activate-account-template');
				$label->appendChild(Widget::Select('members[activate-account-template][]', $activate_account_templates, array('multiple' => 'multiple')));
				$div->appendChild($label);
			}


			// Add Save Changes
			$div->appendChild(Widget::Input('members[event]', 'activate-account', 'hidden'));
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			return '
				<p>This event takes a payment request and parses it.</p>
				' . $div->generate() . '
			';
		}
		
		protected function __trigger(){
			$result = new XMLElement(self::ROOTELEMENT);
			$card_details = $_POST['paypal_save_card'];

			$paypal = ExtensionManager::getInstance('PayPal');

			$members = Symphony::ExtensionManager()->create('members');
			$member = $members->getMemberDriver()->getMemberID();
			
			$card = new CreditCard();
			$card->setType($_POST['card']['card-type'])
				->setNumber($_POST['card']['credit-card'])
				->setExpireMonth($_POST['card']['month'])
				->setExpireYear($_POST['card']['year'])
				->setCvv2($_POST['card']['csc'])
				->setFirstName($_POST['card']['first-name'])
				->setLastName($_POST['card']['last-name']);

			// ### Additional Information
			// Now you can also store the information that could help you connect
			// your users with the stored credit cards.
			// All these three fields could be used for storing any information that could help merchant to point the card.
			// However, Ideally, MerchantId could be used to categorize stores, apps, websites, etc.
			// ExternalCardId could be used for uniquely identifying the card per MerchantId. So, combination of "MerchantId" and "ExternalCardId" should be unique.
			// ExternalCustomerId could be userId, user email, etc to group multiple cards per user.
			// $card->setMerchantId("MyStore1");
			// $card->setExternalCardId("CardNumber123" . uniqid());
			$card->setExternalCustomerId($member);

			// For Sample Purposes Only.
			$request = clone $card;

			// ### Save card
			// Creates the credit card as a resource
			// in the PayPal vault. The response contains
			// an 'id' that you can use to refer to it
			// in future payments.
			// (See bootstrap.php for more on `ApiContext`)
			try {
				$card->create($paypal->getApiContext());
			} catch (Exception $ex) {
				// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
				var_dump($request);
				var_dump($ex);die;
				exit(1);
			}
 			
 			$cardId = $card->getId();

 			$cardDetailsXML = new XMLElement('card-details',null,array(
 																'id'=>$card->getId(), 
 																'month'=>$card->getExpireMonth(), 
 																'year'=>$card->getExpireYear(), 
 																'type'=>$card->getType(), 
 															));

 			$result->appendChild($cardDetailsXML);
 			$result->setAttribute('result','success');
 			// ResultPrinter::printResult("Create Credit Card", "Credit Card", $card->getId(), $request, $card);

			return $result;
		}

	}
