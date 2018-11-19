<?php

class Sual_Forms_Model_System_Config_Source_Pages {
    
    /**
     *
     * @access public
     * @return array
     */
    public function toOptionArray(){
        return array(
            array('value' => 'all', 'label' => __('All')),
            array('value' => 'index', 'label' => __('Home page'))
        );
    }
}
?>
