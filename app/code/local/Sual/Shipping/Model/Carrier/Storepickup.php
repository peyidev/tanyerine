<?php

class Sual_Shipping_Model_Carrier_Storepickup
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'sual_storepickup';
    protected $_isFixed = true;

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag('active')) {
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
        $method->setCarrierTitle('PickUp in Store');
        $method->setMethod('sual_storepickup_'.$storeId);
        $method->setMethodTitle('PickUp in Store '.$storeId);
        $method->setPrice(10);
        $method->setCost(10);
        return $method; 
    }

}