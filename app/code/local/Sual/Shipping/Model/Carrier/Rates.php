<?php

class Sual_Shipping_Model_Carrier_Rates
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'sual_rates';
    protected $_isFixed = true;

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigData('active')) {
            return false;
        }
        
        $result = Mage::getModel('shipping/rate_result');
        if($estafeta = $this->getEstafeta($request)) {
            $result->append($estafeta);
        }

        if($dhl = $this->getDhl($request)) {
            $result->append($dhl);
        }

        return $result;
    }

    public function getAllowedMethods()
    {
        return array('sual_rates' => $this->getConfigData('name'));
    }

    public function getEstafeta($request) 
    {
        $helper =  Mage::helper('sual_integrations/data');
        $response = $helper->callService("mensajeria/cotizar",$this->getShippingAddress('estafeta',$request));
        if($response->result->status) {
            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier('sual_rates');
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod('sual_estafeta');
            $method->setMethodTitle($this->getConfigData('estafeta_title'));
            $method->setPrice($response->result->rate);
            $method->setCost($response->result->rate);
            return $method; 
        } else {
            return false;
        }
    }

    public function getDhl($request) 
    {
        $helper =  Mage::helper('sual_integrations/data');
        $response = $helper->callService("mensajeria/cotizar",$this->getShippingAddress('dhlexpress',$request));
        Mage::log($response,null,'carriers.log');
        if($response->result->status) {
            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier('sual_rates');
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod('sual_dhl');
            $method->setMethodTitle($this->getConfigData('dhl_title'));
            $method->setPrice($response->result->rate);
            $method->setCost($response->result->rate);
            return $method; 
        } else {
            return false;
        }
    }

    public function getShippingAddress($carrier,$request) 
    {
        $quoteAddress = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress();
        $address['carrier'] = $carrier;//"dhlexpress";
        $address['calle'] = $quoteAddress->getStreet();
        $address['colonia'] = $quoteAddress->getNeighborhood();
        $address['codigoPostal'] = $quoteAddress->getPostcode();
        $address['municipio'] = $quoteAddress->getCity();
        $address['ciudad'] = $quoteAddress->getCity();
        $address['estado'] = $quoteAddress->getRegion();
        $address['pais'] = $quoteAddress->getCountry();
        return json_encode($address);
    }
}