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

class Magmodules_Googleshopping_Adminhtml_GoogleshoppingController extends Mage_Adminhtml_Controller_Action
{

    const XPATH_RESULT = 'googleshopping/generate/feed_result';
    const XPATH_BYPASSFLAT = 'googleshopping/generate/bypass_flat';

    /**
     * @var Magmodules_Googleshopping_Helper_Data
     */
    public $helper;

    /**
     * @var Mage_Core_Model_Config
     */
    public $config;

    /**
     * @var Magmodules_Googleshopping_Model_Googleshopping
     */
    public $feed;

    /**
     * Magmodules_Googleshopping_Model_Googleshopping constructor.
     */
    public function _construct()
    {
        $this->helper = Mage::helper('googleshopping');
        $this->config = Mage::getModel('core/config');
        $this->feed = Mage::getModel('googleshopping/googleshopping');
    }

    /**
     *
     */
    public function generateManualAction()
    {
        try {
            if (Mage::getStoreConfig('googleshopping/general/enabled')) {
                $storeId = $this->getRequest()->getParam('store_id');
                if (!empty($storeId)) {
                    $timeStart = microtime(true);
                    /** @var Mage_Core_Model_App_Emulation $appEmulation */
                    $appEmulation = Mage::getSingleton('core/app_emulation');
                    $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                    if ($result = $this->feed->generateFeed($storeId)) {
                        $this->feed->updateConfig($result, 'manual', $timeStart, $storeId);
                        $downloadUrl = $this->getUrl('*/googleshopping/download/store_id/' . $storeId);
                        $msg = $this->helper->__(
                            'Generated feed with %s products. %s',
                            $result['qty'],
                            '<a style="float:right;" href="' . $downloadUrl . '">Download XML</a>'
                        );
                        Mage::getSingleton('adminhtml/session')->addSuccess($msg);
                    } else {
                        $this->config->saveConfig(self::XPATH_RESULT, '', 'stores', $storeId);
                        $msg = $this->helper->__('No products found, make sure your filters are configured with existing values.');
                        Mage::getSingleton('adminhtml/session')->addError($msg);
                    }

                    $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                }
            } else {
                $msg = $this->helper->__('Please enable the extension before generating the xml');
                Mage::getSingleton('adminhtml/session')->addError($msg);
            }
        } catch (\Exception $e) {
            $this->helper->addToLog('previewAction', $e->getMessage());
            if (strpos($e->getMessage(), 'SQLSTATE[42S22]') !== false) {
                $msg = $this->helper->__(
                    'SQLSTATE[42S22]: Column not found, plese go to %s and rebuild required indexes.',
                    '<a href="' . $this->getUrl('adminhtml/process/list') . '">Index Management</a>'
                );
                Mage::getSingleton('adminhtml/session')->addError($msg);
            } else {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('adminhtml/system_config/edit/section/googleshopping');
    }

    /**
     *
     */
    public function previewAction()
    {
        try {
            if (Mage::getStoreConfig('googleshopping/general/enabled')) {
                $storeId = $this->getRequest()->getParam('store_id');

                if (!empty($storeId)) {
                    /** @var Mage_Core_Model_App_Emulation $appEmulation */
                    $appEmulation = Mage::getSingleton('core/app_emulation');
                    $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                    $this->feed->generateFeed($storeId, 'preview');
                    $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

                    $filePath = '';
                    if ($fileName = $this->feed->getFileName('googleshopping', $storeId, 'preview')) {
                        $filePath = Mage::getBaseDir() . DS . 'media' . DS . 'googleshopping' . DS . $fileName;
                    }

                    if (!empty($filePath) && file_exists($filePath)) {
                        $this->getResponse()
                            ->setHttpResponseCode(200)
                            ->setHeader(
                                'Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
                                true
                            )
                            ->setHeader('Pragma', 'no-cache', 1)
                            ->setHeader('Content-type', 'application/force-download')
                            ->setHeader('Content-Length', filesize($filePath))
                            ->setHeader('Content-Disposition', 'attachment' . '; filename=' . basename($filePath));
                        $this->getResponse()->clearBody();
                        $this->getResponse()->sendHeaders();
                        readfile($filePath);
                    } else {
                        $msg = $this->helper->__('Error creating preview XML');
                        Mage::getSingleton('adminhtml/session')->addError($msg);
                        $this->_redirect('adminhtml/system_config/edit/section/googleshopping');
                    }
                }
            } else {
                $msg = $this->helper->__('Please enable the extension before generating the xml');
                Mage::getSingleton('adminhtml/session')->addError($msg);
                $this->_redirect('adminhtml/system_config/edit/section/googleshopping');
            }
        } catch (\Exception $e) {
            $this->helper->addToLog('previewAction', $e->getMessage());
            if (strpos($e->getMessage(), 'SQLSTATE[42S22]') !== false) {
                $msg = $this->helper->__(
                    'SQLSTATE[42S22]: Column not found, plese go to %s and rebuild required indexes.',
                    '<a href="' . $this->getUrl('adminhtml/process/list') . '">Index Management</a>'
                );
                Mage::getSingleton('adminhtml/session')->addError($msg);
            } else {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }

            $this->_redirect('adminhtml/system_config/edit/section/googleshopping');
        }
    }

    /**
     *
     */
    public function addToFlatAction()
    {
        try {
            $nonFlatAttributes = $this->helper->checkFlatCatalog($this->feed->getFeedAttributes());
            foreach ($nonFlatAttributes as $key => $value) {
                Mage::getModel('catalog/resource_eav_attribute')->load($key)->setUsedInProductListing(1)->save();
            }

            $msg = $this->helper->__(
                'Attributes added to Flat Catalog, please %s.',
                '<a href="' . $this->getUrl('adminhtml/process/list') . '">reindex Product Flat Data</a>'
            );
            Mage::getSingleton('adminhtml/session')->addSuccess($msg);
        } catch (\Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('adminhtml/system_config/edit/section/googleshopping');
    }

    /**
     *
     */
    public function bypassFlatAction()
    {
        $this->config->saveConfig(self::XPATH_BYPASSFLAT, 1, 'default', 0);
        $msg = $this->helper->__('Settings saved!');
        Mage::getSingleton('adminhtml/session')->addSuccess($msg);
        $this->_redirect('adminhtml/system_config/edit/section/googleshopping');
    }

    /**
     *
     */
    public function downloadAction()
    {
        try {
            $filePath = '';
            $storeId = $this->getRequest()->getParam('store_id');
            if ($fileName = $this->feed->getFileName('googleshopping', $storeId)) {
                $filePath = Mage::getBaseDir() . DS . 'media' . DS . 'googleshopping' . DS . $fileName;
            }

            if (!empty($filePath) && file_exists($filePath)) {
                $this->getResponse()
                    ->setHttpResponseCode(200)
                    ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0', true)
                    ->setHeader('Pragma', 'no-cache', 1)
                    ->setHeader('Content-type', 'application/force-download')
                    ->setHeader('Content-Length', filesize($filePath))
                    ->setHeader('Content-Disposition', 'attachment' . '; filename=' . basename($filePath));
                $this->getResponse()->clearBody();
                $this->getResponse()->sendHeaders();
                readfile($filePath);
            }
        } catch (\Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirect('adminhtml/system_config/edit/section/googleshopping');
        }
    }

    /**
     *
     */
    public function selftestAction()
    {
        $results = Mage::helper('googleshopping/selftest')->runFeedTests();
        $msg = implode('<br/>', $results);
        Mage::app()->getResponse()->setBody($msg);
    }

    /**
     * @return mixed
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/system/config/googleshopping');
    }

}