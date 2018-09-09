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


class Plumrocket_SocialLogin_Model_Observer
{

    public function controllerActionPredispatch()
    {
        $helper = Mage::helper('pslogin');
        if (!$helper->moduleEnabled()) {
            return;
        }

        // Check email.
        $request = Mage::app()->getRequest();
        $requestString = $request->getRequestString();
        $module = $request->getModuleName();
        $controller = $request->getControllerName();
        $action = $request->getActionName();

        $editUri = 'customer/account/edit';

        switch (true) {
            case (stripos($requestString, 'customer/account/logout') !== false):
                break;

            case $moduleName = (stripos($module, 'customer') !== false) ? 'customer' : null:

                $session = Mage::getSingleton('customer/session');
                $session->getMessages()->deleteMessageByIdentifier('fakeemail');
                if ($session->isLoggedIn() && $helper->isFakeMail(null, true)) {
                    $message = $helper->__('Your account needs to be updated. The email address in your profile is invalid. Please indicate your valid email address by going to the <a href="%s">Account edit page</a>', Mage::getUrl($editUri));

                    switch ($moduleName) {
                        case 'customer':
                            if (stripos($requestString, $editUri) !== false) {
                                // Set new message and red field.
                                $message = $helper->__('Your account needs to be updated. The email address in your profile is invalid. Please indicate your valid email address.');
                            }

                            $session->addUniqueMessages(Mage::getSingleton('core/message')->notice($message)->setIdentifier('fakeemail'));
                            break;
                    }
                }
                break;
        }
    }

    public function customerLogin($observer)
    {
        $helper = Mage::helper('pslogin');
        if (!$helper->moduleEnabled()) {
            return;
        }

        // Set redirect url.
        $redirectUrl = $helper->getRedirectUrl('login');
        Mage::getSingleton('customer/session')->setBeforeAuthUrl($redirectUrl);
    }

    public function customerRegisterSuccess($observer)
    {
        $helper = Mage::helper('pslogin');
        if (!$helper->moduleEnabled()) {
            return;
        }

        $data = Mage::getSingleton('customer/session')->getData('pslogin');

        if (!empty($data['provider']) && !empty($data['timeout']) && $data['timeout'] > time()) {
            $model = Mage::getSingleton("pslogin/{$data['provider']}");

            $customerId = null;
            if ($customer = $observer->getCustomer()) {
                $customerId = $customer->getId();
            }

            if ($customerId) {
                $model->setUserData($data);

                // Remember customer.
                $model->setCustomerIdByUserId($customerId);

                // Load photo.
                if ($helper->photoEnabled()) {
                    $model->setCustomerPhoto($customerId);
                }
            }
        }

        // Show share-popup.
        $helper->showPopup(true);

        // Set redirect url.
        $redirectUrl = $helper->getRedirectUrl('register');
        Mage::app()->getRequest()->setParam(Mage_Core_Controller_Varien_Action::PARAM_NAME_SUCCESS_URL, $redirectUrl);
    }

    public function customerLogout()
    {
        $helper = Mage::helper('pslogin');
        if (!$helper->moduleEnabled()) {
            return;
        }

        Mage::getSingleton('customer/session')->unsLoginProvider();
    }

    /**
     * Customer account controller (before edit account information)
     * Set own encryption class when customer has fake email address
     * Plumrocket_SocialLogin_Model_Encryption - own encription class
     *
     * @param  Varien_Event_Observer $observer
     * @return Varien_Event_Observer
     */
    public function controllerActionPredispatchCustomerAccountEditPost($observer)
    {
        if (Mage::helper('pslogin')->isFakeMail(null, true)) {
            Mage::getConfig()->cleanCache()
                ->setNode(
                    Mage_Core_Helper_Data::XML_PATH_ENCRYPTION_MODEL,
                    'Plumrocket_SocialLogin_Model_Encryption'
                );
        }

        return $this;
    }

    /**
     * Customer account controller (after edit account information)
     * Set native encryption class (Mage_Core_Model_Encryption) when action is done
     *
     * @param  Varien_Event_Observer $observer
     * @return Varien_Event_Observer
     */
    public function controllerActionPostdispatchCustomerAccountEditPost($observer)
    {
        if (Mage::helper('pslogin')->isFakeMail(null, true)) {
            Mage::getConfig()->cleanCache();
        }

        return $this;
    }
}
