<?php

/**
 * Created by PhpStorm.
 * User: PedroLaris
 * Date: 12/02/17
 * Time: 22:00
 */
class Sual_Importer_Adminhtml_ImportexecuteController extends Mage_Adminhtml_Controller_Action
{


    public function indexAction()
    {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('sual_importer/execute'));
        $this->renderLayout();
    }

    public function editAction()
    {

        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('sual_importer/execute');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->_getSession()->addError(
                    Mage::helper('sual_importer')->__('Esta ejecución ya no existe.')
                );
                $this->_redirect('*/*/');
                return;
            }
        }


        $data = $this->_getSession()->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('current_model', $model);

        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('sual_importer/execute_new'));
        $this->renderLayout();
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function saveAction()
    {

        $ejecucionesActivas = Mage::getModel('sual_importer/execute')->getCollection()
            ->addFieldToFilter('tipo_ejecucion', array("eq" => $this->getRequest()->getParam('tipo_ejecucion')))
            ->addFieldToFilter('fin', array('null' => 1))->getFirstItem()->getData();


        if(!empty($ejecucionesActivas['importer_execute_id'])){
            $this->_getSession()->addError("No es posible realizar esta importación. <br /> Existe un proceso de importación similar en ejecución");
            $this->_redirect('*/*/index');
            return;
        }


        $redirectBack = $this->getRequest()->getParam('back', false);
        if ($data = $this->getRequest()->getPost()) {

            $id = $this->getRequest()->getParam('id');
            $model = Mage::getModel('sual_importer/execute');
            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    $this->_getSession()->addError(
                        Mage::helper('sual_importer')->__('Esta ejecución ya no existe.')
                    );
                    $this->_redirect('*/*/index');
                    return;
                }
            }

            // save model
            try {
                $model->addData($data);
                $this->_getSession()->setFormData($data);
                $model->save();
                $this->_getSession()->setFormData(false);
                $this->_getSession()->addSuccess(
                    Mage::helper('sual_importer')->__('La ejecución ha sido programada.')
                );

                if( $this->getRequest()->getParam('tipo_ejecucion') == "import_productos"){
                    $helper = Mage::helper('sual_importer/data');
                    $helper->execute($model->getId(), "frontend");
                    //echo "sh " . Mage::getBaseDir('base') . "shell/import_products.sh frontend executionid\n";
                    //$path =  Mage::getBaseDir('base') . "/shell/import_products.php";
                    //echo shell_exec("sh " . Mage::getBaseDir('base') . "/shell/import_products.sh frontend {$model->getId()} {$path}");
                    //shell_exec("/usr/bin/php  " . Mage::getBaseDir('base') . "/shell/import_products.php --source frontend --executionid " . $model->getId() . " > /var/null &");
                    //shell_exec("/Applications/MAMP/bin/php/php5.6.32/bin/php  " . Mage::getBaseDir('base') . "/shell/import_products.php --source frontend --executionid " . $model->getId() . " > /dev/null &");
                }

            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $redirectBack = true;
            } catch (Exception $e) {
                Mage::helper('sual_importer')->__('Imposible guardar la ejecución.');
                $redirectBack = true;
                Mage::logException($e);
            }

            if ($redirectBack) {
                $this->_redirect('*/*/edit', array('id' => $model->getId()));
                return;
            }
        }
        $this->_redirect('*/*/index');
    }
}