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
 * @copyright   Copyright (c) 2014 Plumrocket Inc. (http://www.plumrocket.com)
 * @license     http://wiki.plumrocket.net/wiki/EULA  End-user License Agreement
 */


class Plumrocket_SocialLogin_AccountController extends Mage_Core_Controller_Front_Action
{

    public function useAction()
    {
        $session = $this->_getSession();
        $session->unsPsloginLog();
        if ($session->isLoggedIn() && !$this->getRequest()->getParam('call')) {
            return $this->_windowClose();
        }

        $type = $this->getRequest()->getParam('type');
        $className = 'Plumrocket_SocialLogin_Model_'. ucfirst($type);
        if (!$type || !class_exists($className)) {
            return $this->_windowClose();
        }

        $model = Mage::getSingleton("pslogin/$type");

        if (!$this->_getHelper()->moduleEnabled() || !$model->enabled()) {
            return $this->_windowClose();
        }

        if ($call = $this->getRequest()->getParam('call')) {
            $this->_getHelper()->apiCall(
                array(
                'type'      => $type,
                'action'    => $call,
                )
            );
        } else {
            $this->_getHelper()->apiCall(null);
        }

        switch($model->getProtocol()) {
            case 'OAuth':
                if ($link = $model->getProviderLink()) {
                    $this->_redirectUrl($link);
                } else {
                    if (Mage::helper('pslogin')->getDebugMode()) {
                        $model->recordLog();
                        $this->_displayError($model);
                        return;
                    } else{
                        $this->getResponse()->setBody($this->__('The Login Application was not configured correctly. If your are the admin of store: Please activate “Enable Logging” in Magento Login Extension and try again to see error details.'));
                    }
                }
                break;

            case 'OpenID':
            case 'BrowserID':
            default:
                return $this->_windowClose();
        }

    }

    public function loginAction()
    {
        $session = $this->_getSession();
        $type = $this->getRequest()->getParam('type');

        // Fix if store view have different domains.
        if ($storeId = $this->_getHelper()->refererStore()) {
            $store = Mage::getModel('core/store')->load($storeId);
            $storeUrl = $store->getBaseUrl();
            if ($store->getId() && $storeUrl != Mage::getBaseUrl()) {
                $this->_getHelper()->refererStore($storeId);
                $this->_redirectUrl($storeUrl . "pslogin/account/login/type/{$type}/?" . http_build_query($this->getRequest()->getParams()));
                return;
            }
        }

        // API.
        $callTarget = false;
        if ($call = $this->_getHelper()->apiCall()) {
            if (isset($call['type']) && $call['type'] == $type && !empty($call['action'])) {
                $_target = explode('.', $call['action'], 3);
                if (count($_target) === 3) {
                    $callTarget = $_target;
                } else {
                    $this->_windowClose();
                    return;
                }
            }
        }

        if ($session->isLoggedIn() && !$callTarget) {
            return $this->_windowClose();
        }

        $className = 'Plumrocket_SocialLogin_Model_'. ucfirst($type);
        if (!$type || !class_exists($className)) {
            return $this->_windowClose();
        }

        $model = Mage::getSingleton("pslogin/$type");

        $responseTypes = $model->getResponseType();
        if (is_array($responseTypes)) {
            $response = array();
            foreach ($responseTypes as $responseType) {
                $response[$responseType] = $this->getRequest()->getParam($responseType);
            }
        } else {
            $response = $this->getRequest()->getParam($responseTypes);
        }

        $model->_setLog($this->getRequest()->getParams());
        if (!$model->loadUserData($response)) {
            if (Mage::helper('pslogin')->getDebugMode()) {
                $model->recordLog();
                $this->_displayError($model);
                return;
            } else {
                $this->getResponse()->setBody($this->__('The Login Application was not configured correctly. If your are the admin of store: Please activate “Enable Logging” in Magento Login Extension and try again to see error details.'));
                return;
            }
        }

        $session->unsPsloginLog();

        // Switch store.
        if ($storeId) {
            Mage::app()->setCurrentStore($storeId);
        }

        // API.
        if ($callTarget) {
            list($module, $controller, $action) = $callTarget;
            $this->_forward($action, $controller, $module, array('pslogin' => $model->getUserData()));
            return;
        }

        if ($customerId = $model->getCustomerIdByUserId()) {
            //Check and replace fakeEmail with normal email
            if ($responseEmail = $model->getUserData('email')) {
                $customer = Mage::getModel('customer/customer')->load($customerId);
                if ($customer->getId() && $this->_getHelper()->isFakeMail($customer->getEmail())) {
                    if ($responseEmail != $customer->getEmail()) {
                        $otherCustomer = Mage::getModel('customer/customer')
                            ->getCollection()
                            ->addFieldToFilter('email', $responseEmail)
                            ->setPageSize(1)
                            ->getFirstItem();

                        if (!$otherCustomer->getId()) {
                            $customer->setEmail($responseEmail)->save();
                        }
                    }
                }
            }

            # Do auth.
            $redirectUrl = $this->_getHelper()->getRedirectUrl();
        } elseif ($customerId = $model->getCustomerIdByEmail()) {
            # Customer with received email was placed in db.
            // Remember customer.
            $model->setCustomerIdByUserId($customerId);
            // System message.
            $url = $this->_getUrl('customer/account/forgotpassword');
            $message = $this->__('Customer with email (%s) already exists in the database. If you are sure that it is your email address, please <a href="%s">click here</a> to retrieve your password and access your account.', $model->getUserData('email'), $url);
            $session->addNotice($message);

            $redirectUrl = $this->_getHelper()->getRedirectUrl();
        } else {
            # Registration customer.
            if ($customerId = $model->registrationCustomer()) {
                # Success.
                // Display system messages (before setCustomerIdByUserId(), because reset messages).
                if ($this->_getHelper()->isFakeMail($model->getUserData('email'))) {
                    $session->addSuccess($this->__('Customer registration successful.'));
                } else {
                    $session->addSuccess($this->__('Customer registration successful. Your password was send to the email: %s', $model->getUserData('email')));
                }

                if ($errors = $model->getErrors()) {
                    foreach ($errors as $error) {
                        $session->addNotice($error);
                    }
                }

                // Dispatch event.
                $this->_dispatchRegisterSuccess($model->getCustomer());

                // Remember customer.
                $model->setCustomerIdByUserId($customerId);

                // Post mail.
                $model->postToMail();

                // Show share-popup.
                $this->_getHelper()->showPopup(true);

                $redirectUrl = $this->_getHelper()->getRedirectUrl('register');
            } else {
                # Error.
                $session->setCustomerFormData($model->getUserData());
                $redirectUrl = $this->_getUrl('customer/account/create', array('_secure' => true));

                if ($errors = $model->getErrors()) {
                    foreach ($errors as $error) {
                        $session->addError($error);
                    }
                }

                // Remember current provider data.
                $session->setData(
                    'pslogin', array(
                    'provider'  => $model->getProvider(),
                    'user_id'   => $model->getUserData('user_id'),
                    'photo'     => $model->getUserData('photo'),
                    'timeout'   => time() + Plumrocket_SocialLogin_Helper_Data::TIME_TO_EDIT,
                    )
                );
            }
        }

        if ($customerId) {
            // Load photo.
            if ($this->_getHelper()->photoEnabled()) {
                $model->setCustomerPhoto($customerId);
            }

            // Loged in.
            if ($session->loginById($customerId)) {
                $session->renewSession();
            }

            // Unset referer link.
            $this->_getHelper()->refererLink(null);

            // Remember provider type (for persona).
            $session->setLoginProvider($model->getProvider());
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->getResponse()->clearHeaders()->setHeader('Content-type', 'application/json', true);
            $this->getResponse()->setBody(
                json_encode(
                    array(
                    'redirectUrl' => $redirectUrl
                    )
                )
            );
        } else {
            $this->getResponse()->setBody($this->_jsWrap('if (window.opener && window.opener.location &&  !window.opener.closed) { window.close(); window.opener.location.href = "'.$redirectUrl.'"; } else { window.location.href = "'.$redirectUrl.'"; }'));

            Mage::dispatchEvent(
                'prsociallogin_login_success',
                array('account_controller' => $this, 'redirectUrl' => $redirectUrl)
            );
        }
    }

    public function runjsAction()
    {
        $this->getResponse()->setBody($this->_jsWrap($this->getRequest()->getPost('js')));
    }

    protected function _windowClose()
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->getResponse()->clearHeaders()->setHeader('Content-type', 'application/json', true);
            $this->getResponse()->setBody(
                json_encode(
                    array(
                    'windowClose' => true
                    )
                )
            );
        } else {
            $this->getResponse()->setBody($this->_jsWrap('window.close();'));
        }

        return true;
    }

    protected function _jsWrap($js)
    {
        return '<html><head></head><body><script type="text/javascript">'.$js.'</script></body></html>';
    }

    protected function _dispatchRegisterSuccess($customer)
    {
        Mage::dispatchEvent(
            'customer_register_success',
            array('account_controller' => $this, 'customer' => $customer)
        );
    }

    protected function _getModel($path = 'pslogin/account', $arguments = array())
    {
        return Mage::getModel($path, $arguments);
    }

    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    protected function _getUrl($url, $params = array())
    {
        return Mage::getUrl($url, $params);
    }

    protected function _getHelper($path = 'pslogin')
    {
        return Mage::helper($path);
    }

    /**
     * Display error in debug mode
     */
    protected function _displayError($model)
    {
        $errorBlock = $this->getLayout()
            ->createBlock('core/template')
            ->setError($model->getErrors())
            ->setTemplate('pslogin/error.phtml');

        $this->getResponse()->setBody($errorBlock->toHtml());
        return $this;
    }
}
