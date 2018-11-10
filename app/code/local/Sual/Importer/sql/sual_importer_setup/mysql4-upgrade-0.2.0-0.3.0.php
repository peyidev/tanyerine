<?php

require_once('app/Mage.php');
Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));
$installer = new Mage_Sales_Model_Mysql4_Setup;
$installer->startSetup();
$installer->addAttribute("order", "id_warehouse", array("type"=>"varchar"));
$installer->addAttribute("quote", "id_warehouse", array("type"=>"varchar"));
$installer->endSetup();