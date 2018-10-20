<?php

class Sual_Shipping_Model_Carrier_Rates
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'sual_rates';
    protected $_isFixed = true;

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $result = Mage::getModel('shipping/rate_result');

        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier('sual_rates');
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod('sual_rates');
        $method->setMethodTitle($this->getConfigData('name'));
        $method->setPrice(5);
        $method->setCost(2);

        $result->append($method);

        return $result;
    }

    public function getAllowedMethods()
    {
        return array('sual_rates' => $this->getConfigData('name'));
    }
}