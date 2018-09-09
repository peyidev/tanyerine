<?php
/**
 * Plumrocket Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the End-user License Agreement
 * that is available through the world-wide-web at this URL:
 * http://wiki.plumrocket.net/wiki/EULA
 * If you are unable to obtain it through the world-wide-web, please
 * send an email to support@plumrocket.com so we can send you a copy immediately.
 *
 * @package     Plumrocket_SocialLogin
 * @copyright   Copyright (c) 2018 Plumrocket Inc. (http://www.plumrocket.com)
 * @license     http://wiki.plumrocket.net/wiki/EULA  End-user License Agreement
 */

class Plumrocket_SocialLogin_Adminhtml_Pslogin_IndexController extends Mage_Adminhtml_Controller_Action
{
    public function downloadAction()
    {
        $fileName   = Plumrocket_SocialLogin_Helper_Data::LOG_FILE_NAME;
        $path = Mage::getBaseDir('log') . "/" . $fileName;

        if (file_exists($path)) {
            $content = file_get_contents($path);
            $this->_prepareDownloadResponse($fileName, $content);
        } else {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('pslogin')->__('The log file is missing.')
            );
            $this->_redirectReferer();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function _isAllowed()
    {
        return parent::_isAllowed();
    }
}
