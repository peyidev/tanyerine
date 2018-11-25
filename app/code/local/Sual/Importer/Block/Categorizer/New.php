<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 8/19/18
 * Time: 4:55 PM
 */
class Sual_Importer_Block_Categorizer_New extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        // $this->_objectId = 'id';
        parent::__construct();
        $this->_blockGroup      = 'sual_importer';
        $this->_controller      = 'categorizer';
        $this->_mode            = 'new';
        $modelTitle = $this->_getModelTitle();
        $this->_updateButton('save', 'label', $this->_getHelper()->__("Categorizar"));
    }

    protected function _getHelper(){
        return Mage::helper('sual_importer');
    }

    protected function _getModel(){
        return Mage::registry('current_model');
    }

    protected function _getModelTitle(){
        return 'Categorización de productos';
    }

    public function getHeaderText()
    {
        $model = $this->_getModel();
        $modelTitle = $this->_getModelTitle();
        if ($model && $model->getId()) {
            return $this->_getHelper()->__("Edit $modelTitle (ID: {$model->getId()})");
        }
        else {
            return $this->_getHelper()->__("New $modelTitle");
        }
    }


    /**
     * Get URL for back (reset) button
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('*/*/index');
    }

    public function getDeleteUrl()
    {
        return $this->getUrl('*/*/delete', array($this->_objectId => $this->getRequest()->getParam($this->_objectId)));
    }

    /**
     * Get form save URL
     *
     * @deprecated
     * @see getFormActionUrl()
     * @return string
     */
    public function getSaveUrl()
    {
        $this->setData('form_action_url', 'save');
        return $this->getFormActionUrl();
    }


}
