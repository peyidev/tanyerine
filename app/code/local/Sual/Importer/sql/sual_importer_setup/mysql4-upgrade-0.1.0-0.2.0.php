<?php


require_once('app/Mage.php');
Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));
$installer = new Mage_Sales_Model_Mysql4_Setup;
$attribute  = array(
    'group'                     => 'General',
    'input'                     => 'text',
    'type'                      => 'text',
    'label'                     => 'Name SAP',
    'source'                    => '',
    'global'                    => 1,
    'visible'                   => 1,
    'required'                  => 0,
    'visible_on_front'          => 0,
    'is_html_allowed_on_front'  => 0,
    'is_configurable'           => true,
    'searchable'                => 0,
    'filterable'                => 0,
    'comparable'                => 0,
    'unique'                    => false,
    'user_defined'              => false,
    'is_user_defined'           => false,
    'used_in_product_listing'   => false
);
$installer->addAttribute('catalog_category', 'name_sap', $attribute);
$installer->endSetup();


?>