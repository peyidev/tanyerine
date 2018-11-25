<?php

/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 8/19/18
 * Time: 4:55 PM
 */
class Sual_Importer_Block_Categorizer_New_Form extends Mage_Adminhtml_Block_Widget_Form
{

    protected function _getModel()
    {
        return Mage::registry('current_model');
    }

    protected function _getHelper()
    {
        return Mage::helper('sual_importer');
    }

    protected function _getModelTitle()
    {
        return 'Importador de categorias';
    }

    protected function _prepareForm()
    {
        $model = $this->_getModel();
        $modelTitle = $this->_getModelTitle();
        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save'),
            'method' => 'post'
        ));

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $this->_getHelper()->__("$modelTitle Information"),
            'class' => 'fieldset-wide',
        ));

        if ($model && $model->getId()) {
            $modelPk = $model->getResource()->getIdFieldName();
            $fieldset->addField($modelPk, 'hidden', array(
                'name' => $modelPk,
            ));
        }

        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addIsActiveFilter()
            ->addAttributeToSort('name','ASC');

        $cats = array();
        foreach($categories as $cat) {
            $cats[$cat->getId()] =  $cat->getName() . " - (" . $cat->getId() . ")";
        }

        $fieldset->addField('category_id', 'select' /* select | multiselect | hidden | password | ...  */,
            array(
                'name' => 'category_id',
                'label' => $this->_getHelper()->__('Categorías'),
                'title' => $this->_getHelper()->__('Selecciona la categoría a la cual quieres importar productos'),
                'required' => true,
                'options' => $cats,
            ));

        $fieldset->addField('products','textarea',
            array(
                'name' => 'products',
                'label' => $this->_getHelper()->__('Productos a importar'),
                'title' => $this->_getHelper()->__('Agrega SKUs delimitados por coma'),
                'required' => true,
            ));

        if ($model) {
            $form->setValues($model->getData());
        }
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

}
