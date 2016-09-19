<?php


    if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');
    require_once(EXTENSIONS . '/paypal/vendor/autoload.php');
    use PayPal\Api\CreditCard;


class datasourcepaypal_saved_cards extends Datasource
{
    public $dsParamROOTELEMENT = 'paypal-saved-cards';
    public $dsParamPAGINATERESULTS = 'no';
    public $dsParamSTARTPAGE = '1';
    public $dsParamREDIRECTONEMPTY = 'no';
    public $dsParamSORT = 'system:id';
    public $dsParamASSOCIATEDENTRYCOUNTS = 'no';
    public $dsParamINCLUDEDELEMENTS = array();
    public $dsParamSOURCE = 0;
    public $dsParamHTMLENCODE = '';

    public function about()
    {
        return array(
            'name' => 'Paypal: Saved Cards'
        );
    }

    public function getSource()
    {
        return $this->dsParamSOURCE;
    }

    public function allowEditorToParse()
    {
        return false;
    }

    public function execute(&$param_pool)
    {
        $result = new XMLElement($this->dsParamROOTELEMENT);

        /// ### List All Credit Cards
        // (See bootstrap.php for more on `ApiContext`)
        try {

            $members = Symphony::ExtensionManager()->create('members');
            $member = $members->getMemberDriver()->getMemberID();
            // $member = $members->getMemberDriver()->isLoggedIn();

            if (isset($member) && $member != 0 ){
                $paypal = ExtensionManager::getInstance('PayPal');
                // ### Parameters to Filter
                // There are many possible filters that you could apply to it. For complete list, please refer to developer docs at above link.
                $params = array(
                    "sort_by" => "create_time",
                    "sort_order" => "desc",
                    // "merchant_id" => "MyStore1",  // Filtering by MerchantId set during CreateCreditCard.
                    "external_customer_id" => $member  // Filtering by MerchantId set during CreateCreditCard.
                );
                $cards = CreditCard::all($params, $paypal->getApiContext());

                foreach ($cards->getItems() as $key => $card) {
                    $cardNumber = rtrim(chunk_split($card->getNumber(), 4, '-'), '-');
                    $cardDetailsXML = new XMLElement('entry',$cardNumber,array(
                                                                        'id'=>$card->getId(), 
                                                                        'month'=>$card->getExpireMonth(), 
                                                                        'year'=>$card->getExpireYear(), 
                                                                        'type'=>$card->getType(), 
                                                                    ));
                    $result->appendChild($cardDetailsXML);
                }
            }
        } catch (Exception $ex) {
            // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
            // ResultPrinter::printError("List All Credit Cards", "CreditCardList", null, $params, $ex);
            var_dump($params);
            var_dump($ex);die;
            exit(1);
        }
        
       
        return $result;
    }
}