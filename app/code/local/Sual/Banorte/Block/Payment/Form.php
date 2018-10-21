<?php

class Sual_Banorte_Block_Payment_Form extends Mage_Payment_Block_Form_Cc {

    /**
     * Construct and assign the template to use
     * @access protected
     */
    protected function _construct(){
        parent::_construct();
        $this->setTemplate('banorte/payment/form.phtml');
    }
    
    /**
     *
     * @access protected
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote() {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Evaluate if the defer pay option is active
     * @access public
     * @return boolean
     */
    public function canDefer(){
        $model = Mage::getSingleton('banorte/payment');
        $result = false;
        if($model->getConfigData('installments') !== '') {
            $result = true;
        }

        return $result;
    }

    /**
     * Retrieve the options to pay in months
     * @access public
     * @return array
     */
    public function getInstallments(){
        $model = Mage::getModel('banorte/payment_options_months');
        return $model->getDeferredPay();
    }
}
?>
