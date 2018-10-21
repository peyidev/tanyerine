<?php

class Sual_Banorte_Model_System_Config_Source_Months {
    
    /**
     *
     * @access public
     * @return array
     */
    public function toOptionArray(){
        return array(
            array('value' => 3, 'label' => '3 ' . Mage::helper('banorte')->__('Months')),
            array('value' => 6, 'label' => '6 ' . Mage::helper('banorte')->__('Months')),
            array('value' => 9, 'label' => '9 ' . Mage::helper('banorte')->__('Months')),
            array('value' => 12, 'label' => '12 ' . Mage::helper('banorte')->__('Months'))
        );
    }
}
?>
