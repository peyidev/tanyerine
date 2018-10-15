<?php
/**
* @category   Entrepids
* @package    Entrepids_Completeaddress
* @author     miguel.perez@entrepids.com
* @website    http://www.entrepids.com
*/
$installer = $this; 
$installer->startSetup();
 
$installer->addAttribute('customer_address', 'neighborhood', array(
    'type' => 'varchar',
    'input' => 'text',
    'label' => 'Neighborhood',
    'global' => 1,
    'visible' => 1,
    'required' => 0,
    'user_defined' => 0,
    'visible_on_front' => 1
));
Mage::getSingleton('eav/config')
    ->getAttribute('customer_address', 'neighborhood')
    ->setData('used_in_forms', array('adminhtml_customer_address','customer_account_edit','customer_address_edit','customer_register_address'))
    ->save();

$qa = $this->getTable('sales/quote_address');
$installer->run("
    ALTER TABLE  $qa ADD  `neighborhood` varchar(255) NULL
");
 
$oa = $this->getTable('sales/order_address');
$installer->run("
    ALTER TABLE  $oa ADD  `neighborhood` varchar(255) NULL
");

Mage::getConfig()->deleteConfig('customer/address_templates/text');
Mage::getConfig()->deleteConfig('customer/address_templates/oneline');
Mage::getConfig()->deleteConfig('customer/address_templates/html');
Mage::getConfig()->deleteConfig('customer/address_templates/pdf');

$installer->endSetup();