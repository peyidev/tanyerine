<?php

/**
 * Created by PhpStorm.
 * User: PedroLaris
 * Date: 12/02/17
 * Time: 22:00
 */
class Sual_Importer_Adminhtml_CategoryimporterController extends Mage_Adminhtml_Controller_Action
{


    public function indexAction()
    {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('sual_importer/categorizer_new'));
        $this->renderLayout();
    }


    public function saveAction()
    {
        $redirectBack = $this->getRequest()->getParam('back', false);

        if ($data = $this->getRequest()->getPost()) {


            try {

                $idCat = $this->getRequest()->getParam('category_id');
                $products = explode(',', $this->getRequest()->getParam('products'));

                $productCollection = Mage::getModel('catalog/product')->getCollection()
                    ->addAttributeToFilter('sku', array('in' => $products));


                foreach ($productCollection as $product) {
                    Mage::getSingleton('catalog/category_api')
                        ->assignProduct($idCat, $product->getId());
                }

                $this->_getSession()->setFormData(false);
                $this->_getSession()->addSuccess(
                    Mage::helper('sual_importer')->__('La categorización ha sido realizada.')
                );

            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $redirectBack = true;
            } catch (Exception $e) {
                Mage::helper('sual_importer')->__('Imposible categorizar.');
                $redirectBack = true;
                Mage::logException($e);
            }
        }
        $this->_redirect('*/*/index');
    }
}