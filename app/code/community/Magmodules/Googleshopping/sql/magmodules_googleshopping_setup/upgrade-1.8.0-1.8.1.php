<?php
/**
 * Magmodules.eu - http://www.magmodules.eu.
 *
 * NOTICE OF LICENSE
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://www.magmodules.eu/MM-LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@magmodules.eu so we can send you a copy immediately.
 *
 * @category      Magmodules
 * @package       Magmodules_Googleshopping
 * @author        Magmodules <info@magmodules.eu>
 * @copyright     Copyright (c) 2018 (http://www.magmodules.eu)
 * @license       https://www.magmodules.eu/terms.html  Single Service License
 */

/** @var $installer Mage_Catalog_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();
if (!$installer->getAttributeId('catalog_category', 'googleshopping_exclude')) {
    $installer->addAttribute(
        'catalog_category', 'googleshopping_exclude', array(
            'group'        => 'Feeds',
            'input'        => 'select',
            'type'         => 'int',
            'source'       => 'eav/entity_attribute_source_boolean',
            'label'        => 'Exclude from Google Shopping Product Type',
            'required'     => false,
            'user_defined' => true,
            'visible'      => true,
            'global'       => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
            'position'     => 99,
        )
    );
}

$installer->endSetup();

