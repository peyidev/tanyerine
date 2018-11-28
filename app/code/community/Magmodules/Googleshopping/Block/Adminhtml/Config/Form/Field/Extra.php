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
class Magmodules_Googleshopping_Block_Adminhtml_Config_Form_Field_Extra
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{

    /**
     * @var array
     */
    protected $_renders = array();

    /**
     * Magmodules_Googleshopping_Block_Adminhtml_Config_Form_Field_Extra constructor.
     */
    public function __construct()
    {
        $layout = Mage::app()->getFrontController()->getAction()->getLayout();

        $rendererAttribute = $layout->createBlock(
            'googleshopping/adminhtml_config_form_renderer_select',
            '',
            array('is_render_to_js_template' => true)
        );

        $rendererAttribute->setOptions(
            Mage::getModel('googleshopping/adminhtml_system_config_source_attribute')->toOptionArray()
        );

        $rendererAction = $layout->createBlock(
            'googleshopping/adminhtml_config_form_renderer_select',
            '',
            array('is_render_to_js_template' => true)
        );
        $rendererAction->setOptions(
            Mage::getModel('googleshopping/adminhtml_system_config_source_action')->toOptionArray()
        );

        $this->addColumn(
            'name', array(
                'label' => Mage::helper('googleshopping')->__('Field Name'),
                'style' => 'width:120px',
            )
        );

        $this->addColumn(
            'attribute', array(
                'label'    => Mage::helper('googleshopping')->__('Attribute'),
                'style'    => 'width:180px',
                'renderer' => $rendererAttribute
            )
        );

        $this->addColumn(
            'action', array(
                'label'    => Mage::helper('googleshopping')->__('Actions'),
                'style'    => 'width:120px',
                'renderer' => $rendererAction
            )
        );

        $this->_renders['attribute'] = $rendererAttribute;
        $this->_renders['action'] = $rendererAction;

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('googleshopping')->__('Add Field');
        parent::__construct();
    }

    /**
     * @param Varien_Object $row
     */
    protected function _prepareArrayRow(Varien_Object $row)
    {
        foreach ($this->_renders as $key => $render) {
            $row->setData(
                'option_extra_attr_' . $render->calcOptionHash($row->getData($key)),
                'selected="selected"'
            );
        }
    }

}