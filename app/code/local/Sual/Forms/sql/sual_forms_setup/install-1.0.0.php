<?php
$this->startSetup();
$this->addAttribute('customer', 'phone', array(
    'type'      => 'varchar',
    'label'     => 'Phone Number',
    'input'     => 'text',
    'position'  => 120,
    'required'  => false,
    'is_system' => 0,
));

$attribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'phone');
$attribute->setData('used_in_forms', array(
    'adminhtml_customer',
    'checkout_register',
    'customer_account_create',
    'customer_account_edit',
));
$attribute->setData('is_user_defined', 0);
$attribute->save();

$this->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'name_sap', array(
    'group'         => 'General Information',
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'Sap name',
    'backend'       => '',
    'visible'       => false,
    'required'      => false,
    'visible_on_front' => false,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
));

$this->endSetup();