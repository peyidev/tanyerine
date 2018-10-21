<?php

class Sual_Banorte_Model_Payment_Options_Months extends Mage_Core_Model_Abstract {

    /**
     * Months array
     * @var array
     */
    protected $_deferredPay = array();

    /**
     * Retrieve the months array
     * @access public
     * @return array
     */
    public function getDeferredPay()
    {
        $instance = Mage::getModel('banorte/payment');
        $this->_deferredPay = explode(',', $instance->getConfigData('installments'));        
        return $this->_deferredPay;
    }
}
?>
