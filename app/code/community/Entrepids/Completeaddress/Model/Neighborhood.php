<?php
/**
* @category   Entrepids
* @package    Entrepids_Completeaddress
* @author     miguel.perez@entrepids.com
* @website    http://www.entrepids.com
*/
class Entrepids_Completeaddress_Model_Neighborhood extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('completeaddress/neighborhood');
    }
}