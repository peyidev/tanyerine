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

class Magmodules_Googleshopping_Model_Source_Attribute
{

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $backendTypes = array(
            'text',
            'select',
            'textarea',
            'date',
            'int',
            'boolean',
            'static',
            'varchar',
            'decimal'
        );

        $optionArray = array();

        /** @var Mage_Catalog_Model_Resource_Product_Attribute_Collection $attributes */
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->addFieldToFilter('backend_type', $backendTypes);

        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('- Product ID'),
            'value' => 'entity_id'
        );
        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('- Final Price'),
            'value' => 'final_price'
        );

        foreach ($attributes as $attribute) {
            $optionArray[] = array(
                'label' => str_replace("'", "", $attribute->getData('frontend_label')),
                'value' => $attribute->getData('attribute_code')
            );
        }

        return $optionArray;
    }

}