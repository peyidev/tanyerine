<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 8/19/18
 * Time: 4:53 PM
 */ 
class Sual_Importer_Model_Resource_Execute extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct()
    {
        $this->_init('sual_importer/importer_execute', 'importer_execute_id');
    }

}