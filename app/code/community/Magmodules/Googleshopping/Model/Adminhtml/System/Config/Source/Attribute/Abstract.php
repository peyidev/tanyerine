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

class Magmodules_Googleshopping_Model_Adminhtml_System_Config_Source_Attribute_Abstract
{

    /**
     * Options array
     *
     * @var array
     */
    public $options = array();

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $options[] = array('value' => '', 'label' => Mage::helper('googleshopping')->__('-- None'));
            $options[] = $this->getAttributesArray();
            $actions = $this->getActionsArray();
            if (!empty($actions)) {
                $options[] = $actions;
            }

            $this->options = $options;
        }

        return $this->options;
    }

    /**
     * @return array
     */
    public function getAttributesArray()
    {
        $optionArray = $this->getExtraFields();
        $excludes = $this->getExludeAttributes();
        $backendTypes = $this->getBackendTypes();
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->setOrder('frontend_label', 'ASC')
            ->addFieldToFilter('backend_type', $backendTypes)
            ->addFieldToFilter('attribute_code', array('nin' => $excludes));

        foreach ($attributes as $attribute) {
            $optionArray[] = array(
                'value' => $attribute->getData('attribute_code'),
                'label' => $this->getLabel($attribute),
            );
        }

        usort(
            $optionArray, function ($a, $b) {
            return strcmp($a["label"], $b["label"]);
            }
        );

        return array(
            'label'         => Mage::helper('googleshopping')->__('Atttibutes'),
            'value'         => $optionArray,
            'optgroup-name' => Mage::helper('googleshopping')->__('Atttibutes')
        );
    }

    /**
     * @return array
     */
    public function getExtraFields()
    {
        $optionArray = array();

        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('Product ID'),
            'value' => 'entity_id'
        );
        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('Final Price'),
            'value' => 'final_price'
        );
        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('Product Type'),
            'value' => 'type_id'
        );
        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('Attribute Set ID'),
            'value' => 'attribute_set_id'
        );
        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('Minumun Sales Quantity'),
            'value' => 'min_sale_qty'
        );
        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('Is Salable'),
            'value' => 'is_salable'
        );
        $optionArray[] = array(
            'label' => Mage::helper('googleshopping')->__('Qty'),
            'value' => 'qty'
        );
        return $optionArray;
    }

    /**
     * @return array
     */
    public function getExludeAttributes()
    {
        return array(
            'compatibility',
            'gallery',
            'installation',
            'language_support',
            'country_of_manufacture',
            'links_title',
            'current_version',
            'custom_design',
            'custom_layout_update',
            'gift_message_available',
            'image',
            'image_label',
            'media_gallery',
            'msrp_display_actual_price_type',
            'msrp_enabled',
            'options_container',
            'price_view',
            'page_layout',
            'samples_title',
            'sku_type',
            'tier_price',
            'url_key',
            'small_image',
            'small_image_label',
            'thumbnail',
            'thumbnail_label',
            'recurring_profile',
            'version_info',
            'category_ids',
            'has_options',
            'required_options',
            'url_path',
            'updated_at',
            'weight_type',
            'sku_type',
            'link_exist',
            'old_id',
            'price_type',
            'price',
            'special_price',
            'final_price'
        );
    }

    /**
     * @return array
     */
    public function getBackendTypes()
    {
        return array('text', 'select', 'textarea', 'date', 'int', 'boolean', 'static', 'varchar', 'decimal');
    }

    /**
     * @param $attribute
     *
     * @return mixed|string
     */
    public function getLabel($attribute)
    {
        if ($attribute->getData('frontend_label')) {
            $label = str_replace("'", "", $attribute->getData('frontend_label'));
        } else {
            $label = str_replace("'", "", $attribute->getData('attribute_code'));
        }

        return trim($label);
    }

    /**
     * @return array
     */
    public function getActionsArray()
    {
        $actions[] = array(
            'value' => 'mm-actions-custom',
            'label' => Mage::helper('googleshopping')->__('Custom Expression')
        );
        $actions[] = array(
            'value' => 'mm-actions-conditional',
            'label' => Mage::helper('googleshopping')->__('Conditional Expression')
        );

        return array(
            'label'         => Mage::helper('googleshopping')->__('Conditions'),
            'value'         => $actions,
            'optgroup-name' => Mage::helper('googleshopping')->__('Conditions')
        );
    }

}