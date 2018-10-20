<?php

class Sual_Banorte_Block_Payment_Info_Cc extends Mage_Payment_Block_Info_Cc {

    /**
     * Prepare credit card related payment info. Overwrite
     * @access  protected
     * @param Varien_Object|array $transport
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        
        $transport = parent::_prepareSpecificInformation($transport);
        $data = array();
        
        if ($ccType = $this->getCcTypeName()) {
            $data[Mage::helper('banorte')->__('Credit Card Type')] = $ccType;
        }
        
        if ($this->getInfo()->getCcLast4()) {
            $data[Mage::helper('banorte')->__('Credit Card Number')] = sprintf('xxxx-%s', $this->getInfo()->getCcLast4());
        }
      
        if($this->canDefer()){
            if($months = $this->getCcDeferred()){
                $data[Mage::helper('banorte')->__('Months')] = $months;
            }
            
            if($deferred = $this->getPayDeferred()){
                $data[Mage::helper('banorte')->__('Monthly Payment')] = $deferred;
            }
        }
        
        if (!$this->getIsSecureMode()) {
            if ($ccSsIssue = $this->getInfo()->getCcSsIssue()) {
                $data[Mage::helper('banorte')->__('Switch/Solo/Maestro Issue Number')] = $ccSsIssue;
            }
            
            $year = $this->getInfo()->getCcSsStartYear();
            $month = $this->getInfo()->getCcSsStartMonth();
            
            if ($year && $month) {
                $data[Mage::helper('banorte')->__('Switch/Solo/Maestro Start Date')] =  $this->_formatCardDate($year, $month);
            }
        }
        
        return $transport->setData(array_merge($data, $transport->getData()));
    }

    /**
     * Retrieve months and return pay by month
     * @access  public
     * @return string|null
     */
    public function getPayDeferred(){
        if($this->getInfo()->getQuote() !== null){
            $total = $this->getInfo()
                    ->getQuote()
                    ->getGrandTotal();
            $months = $this->getCcDeferred();
            return Mage::getModel('directory/currency')->format($total / $months, null, false);
        }
        
        return null;
    }

    /**
     * Retrieve from payment data the quantity of months to pay
     * @access public
     * @return  integer
     */
    public function getCcDeferred() {
        return $this->getInfo()
                ->getCcDeferred();
    }

    /**
     * Evaluate if the defer pay option is active
     * @access public
     * @return bool
     */
    public function canDefer(){
        $instance = Mage::getSingleton('banorte/payment');
        
        if($instance->getConfigData('months') && $instance->getConfigData('installments') != "" && $this->getCcDeferred() > 1){
            return true;
        }
        
        return false;
    }
}
?>
