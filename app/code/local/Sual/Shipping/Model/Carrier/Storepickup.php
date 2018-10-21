<?php

class Sual_Shipping_Model_Carrier_Storepickup
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'sual_storepickup';
    protected $_isFixed = true;

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigData('active')) {
            return false;
        }

        $result = Mage::getModel('shipping/rate_result');
        if($storepickup = $this->getStorePickUp(1)) {
            $result->append($storepickup);
        }
        if($storepickup = $this->getStorePickUp(2)) {
            $result->append($storepickup);
        }
        if($storepickup = $this->getStorePickUp(3)) {
            $result->append($storepickup);
        }
        if($storepickup = $this->getStorePickUp(4)) {
            $result->append($storepickup);
        }
        return $result;
    }

    public function getAllowedMethods()
    {
        return array('sual_rates' => $this->getConfigData('name'));
    }

    public function getStorePickUp($storeId) 
    {
        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier('sual_storepickup');
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod('sual_storepickup_'.$storeId);
        $method->setMethodTitle($this->getConfigData('store'.$storeId));
        $method->setPrice(0);
        $method->setCost(0);
        return $method; 
    }

}