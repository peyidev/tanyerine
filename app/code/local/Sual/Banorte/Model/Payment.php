<?php
class Sual_Banorte_Model_Payment extends Mage_Payment_Model_Method_Cc {

    protected $_code = 'banorte';
    protected $_formBlockType = 'banorte/payment_form';
    protected $_infoBlockType = 'banorte/payment_info_cc';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_order;
    protected $_canReviewPayment  = true;

    /**
     * Returns controller which defaults to index action
     * @access public
     * @return string
     */
    // public function getOrderPlaceRedirectUrl() {
     //   return Mage::getUrl('banorte/result/result');
    // }

    /**
     * Authorize
     * @access public
     * @param Varien_Object $payment
     * @param  float  $amount
     */
    public function authorize(Varien_Object $payment, $amount) 
    {
        

        $response = $this->createPayment();
        if($response->data->approved) {
            $payment->setIsTransactionClosed(true);
            $payment->setTransactionId("sdsdasd");
        } else{
            Mage::throwException($this->__('Error procesing the order'));
        }
        return $this;
    }

    private function createPayment() 
    {
        $billing = $this->_getOrder()->getBillingAddress();
        $helper =  Mage::helper('sual_integrations/data');
        $cart = Mage::app()->getRequest()->getParam('payment');
        $params = array();
        $params['BillTo_firstName'] = $cart['firstname'];
        $params['BillTo_lastName'] = $cart['lastname'];
        $params['BillTo_street'] = str_replace(' ', '', $billing->getStreet(1));
        $params['BillTo_streetNumber'] = str_replace(' ', '', $billing->getStreet(2));
        $params['BillTo_streetNumber2'] =str_replace(' ', '', $billing->getStreet(3));
        $params['BillTo_street2Col'] = $billing->getNeighborhood();
        $params['BillTo_street2Del'] = $billing->getCity();
        $params['BillTo_city'] = $billing->getCity();
        $params['BillTo_state'] = $billing->getRegion();
        $params['BillTo_country'] = $billing->getCountryId();
        $params['BillTo_phoneNumber'] = $billing->getTelephone();
        $params['BillTo_postalCode'] = $billing->getPostcode();
        $params['BillTo_email'] = $billing->getEmail();
 
        if ($cart['cc_type'] == "VI") {
            $cardtype = "001";
        } else if ($cart['cc_type'] == "MC") {
            $cardtype = "002";
        } else {
            $cardtype = "";
        }
        //002 PARA MASTERCARD - 001 PARA VISA
        $params['Card_accountNumber'] = $cart['cc_number'];
        $params['Card_cardType'] = $cardtype;
        $params['Card_expirationMonth'] = $cart['cc_exp_month'];
        $params['Card_expirationYear'] = $cart['cc_exp_year'];
        $params['Card_cardCCV'] = $cart['cc_cid'];
        $params['PurchaseTotals_grandTotalAmount'] = $this->_getOrder()->getGrandTotal();
        //0 3 6

        $params['Card_cardPromotion'] = (int)$cart['cc_deferred'];
        $params['DeviceFingerprintID'] = rand(10000000, 19999999);
        return $helper->callService("procesa/banorte",$params);
    } 

    /**
     * Retrieve information from payment configuration
     * @access public
     * @param   string $field
     * @return  mixed
     */
    public function getConfigData($field, $storeId = null) {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }
        $path = 'payment/' . $this->getCode() . '/' . $field;
        return Mage::getStoreConfig($path, $storeId);
    }
    
    /**
     * Assign data to info model instance
     * @access public
     * @param   mixed $data
     */
    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        
        $info = $this->getInfoInstance();
        $info->setCcType($data->getCcType())
            ->setCcOwner($data->getCcOwner())
            ->setCcLast4(substr($data->getCcNumber(), -4))
            ->setCcNumber($data->getCcNumber())
            ->setCcCid($data->getCcCid())
            ->setCcExpMonth($data->getCcExpMonth())
            ->setCcExpYear($data->getCcExpYear())
            ->setCcSsIssue($data->getCcSsIssue())
            ->setCcSsStartMonth($data->getCcSsStartMonth())
            ->setCcSsStartYear($data->getCcSsStartYear())
            ->setCcDeferred(($data->getCcDeferred() > 1) ? $data->getCcDeferred() : null);
        return $this;
    }

    /**
     *
     * @access
     * @return Mage_Sales_Model_Order
     */
    protected function _getOrder() {
        if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = Mage::getModel('sales/order')
                    ->loadByIncrementId(
                            $paymentInfo->getOrder()
                                ->getRealOrderId()
            );
        }
        
        return $this->_order;
    }

    /**
     *
     * @access protected
     * @param array | string $data
     * @return array | string
     */
    protected function _utf8Encoded($data){
        $encoded =array();
        
        if(is_array($data)){
            foreach($data as $key => $value){
                $encoded[utf8_encode($key)] = utf8_encode($value);
            }
            
            return $encoded;
        } else {
            return utf8_encode($data);
        }
    }
    /*
     * 
     */
    public function validate()
    {
        /*
        * calling parent validate function
        */
        //parent::validate();

        $info = $this->getInfoInstance();
       // var_dump($info);
        $errorMsg = false;
        $availableTypes = explode(',',$this->getConfigData('cctypes'));

        $ccNumber = $info->getCcNumber();

        // remove credit card number delimiters such as "-" and space
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        $ccType = '';

        if (in_array($info->getCcType(), $availableTypes)){
            if ($this->validateCcNum($ccNumber)
                // Other credit card type number validation
                || ($this->OtherCcType($info->getCcType()) && $this->validateCcNumOther($ccNumber))) {

                $ccType = 'OT';
                $ccTypeRegExpList = array(
                    //Solo, Switch or Maestro. International safe
                    /*
                    // Maestro / Solo
                    'SS'  => '/^((6759[0-9]{12})|(6334|6767[0-9]{12})|(6334|6767[0-9]{14,15})'
                               . '|(5018|5020|5038|6304|6759|6761|6763[0-9]{12,19})|(49[013][1356][0-9]{12})'
                               . '|(633[34][0-9]{12})|(633110[0-9]{10})|(564182[0-9]{10}))([0-9]{2,3})?$/',
                    */
                    // Solo only
                    'SO' => '/(^(6334)[5-9](\d{11}$|\d{13,14}$))|(^(6767)(\d{12}$|\d{14,15}$))/',
                    'SM' => '/(^(5[0678])\d{11,18}$)|(^(6[^05])\d{11,18}$)|(^(601)[^1]\d{9,16}$)|(^(6011)\d{9,11}$)'
                            . '|(^(6011)\d{13,16}$)|(^(65)\d{11,13}$)|(^(65)\d{15,18}$)'
                            . '|(^(49030)[2-9](\d{10}$|\d{12,13}$))|(^(49033)[5-9](\d{10}$|\d{12,13}$))'
                            . '|(^(49110)[1-2](\d{10}$|\d{12,13}$))|(^(49117)[4-9](\d{10}$|\d{12,13}$))'
                            . '|(^(49118)[0-2](\d{10}$|\d{12,13}$))|(^(4936)(\d{12}$|\d{14,15}$))/',
                    // Visa
                    'VI'  => '/^4[0-9]{12}([0-9]{3})?$/',
                    // Master Card
                    'MC'  => '/^5[1-5][0-9]{14}$/',
                    // American Express
                    'AE'  => '/^3[47][0-9]{13}$/',
                    // Discovery
                    'DI'  => '/^6011[0-9]{12}$/',
                    // JCB
                    'JCB' => '/^(3[0-9]{15}|(2131|1800)[0-9]{11})$/'
                );

                foreach ($ccTypeRegExpList as $ccTypeMatch=>$ccTypeRegExp) {
                    if (preg_match($ccTypeRegExp, $ccNumber)) {
                        $ccType = $ccTypeMatch;
                        break;
                    }
                }

                if (!$this->OtherCcType($info->getCcType()) && $ccType!=$info->getCcType()) {
                    $errorMsg = Mage::helper('payment')->__('Credit card number mismatch with credit card type.');
                }
            }
            else {
                $errorMsg = Mage::helper('payment')->__('Invalid Credit Card Number');
            }

        }
        else {
           // echo "3:" . $info->getCcType() . "<br>" . (int)in_array($info->getCcType(), $availableTypes) . "------------<br>"; 
            $errorMsg = Mage::helper('payment')->__('Credit card type is not allowed for this payment method.');
        }

        //validate credit card verification number
        if ($errorMsg === false && $this->hasVerification()) {
            $verifcationRegEx = $this->getVerificationRegEx();
            $regExp = isset($verifcationRegEx[$info->getCcType()]) ? $verifcationRegEx[$info->getCcType()] : '';
            if (!$info->getCcCid() || !$regExp || !preg_match($regExp ,$info->getCcCid())){
                echo $info->getCcCid() . "<br>";
                $errorMsg = Mage::helper('payment')->__('Please enter a valid credit card verification number.');
            }
        }
         
        if (!$this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth())) {
            $errorMsg = Mage::helper('payment')->__('Incorrect credit card expiration date.');
        }

        if($errorMsg){
           // Mage::throwException($errorMsg);
        }

        //This must be after all validation conditions
        if ($this->getIsCentinelValidationEnabled()) {
            $this->getCentinelValidator()->validate($this->getCentinelValidationData());
        }
        return $this;
    }


    public function acceptPayment(Mage_Payment_Model_Info $payment) {
        parent::acceptPayment($payment);
        //perform gateway actions to remove Fraud flags. Capture should not occur here
        return true;
        //returning true will trigger a capture on any existing invoices, otherwise the admin can manually Invoice the order
    }

    public function denyPayment(Mage_Payment_Model_Info $payment) {
        parent::denyPayment($payment);
        //if your payment gateway supports it, you should probably void any pre-auth
        return true;
    }

}
?>
