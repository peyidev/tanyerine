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
 * @copyright   Copyright (c) 2017 Plumrocket Inc. (http://www.plumrocket.com)
 * @license     http://wiki.plumrocket.net/wiki/EULA  End-user License Agreement
 */


class Plumrocket_SocialLogin_Model_Twitter extends Plumrocket_SocialLogin_Model_Account
{
    protected $_type = 'twitter';

    const URL_REQUEST_TOKEN = 'https://api.twitter.com/oauth/request_token';
    const URL_AUTHORIZE = 'https://api.twitter.com/oauth/authorize';
    const URL_ACCESS_TOKEN = 'https://api.twitter.com/oauth/access_token';
    const URL_ACCOUNT_DATA = 'https://api.twitter.com/1.1/account/verify_credentials.json';

    protected $_responseType = array('oauth_token', 'oauth_verifier');

    protected $_fields = array(
                    'user_id' => 'id',
                    'firstname' => 'name',
                    'lastname' => 'name2',
                    'email' => 'email',
                    'dob' => null,
                    'gender' => null,
                    'photo' => 'profile_image_url',
                );

    protected $_buttonLinkParams = null;

    protected $_popupSize = array(630, 650);

    public function _construct()
    {
        parent::_construct();
    }

    public function getProviderLink()
    {
        $token = $this->_getStartToken();
        if (!empty($token['oauth_token'])) {
            $this->_buttonLinkParams = self::URL_AUTHORIZE .'?oauth_token='. $token['oauth_token'];
        }

        return parent::getProviderLink();
    }

    // Step 2.
    public function loadUserData($response)
    {
        if (empty($response['oauth_token']) || empty($response['oauth_verifier'])) {
            return false;
        }

        $data = array();
        $session = Mage::getSingleton('customer/session');

        $oauthNonce = md5(uniqid(rand(), true));
        $oauthTimestamp = time();

        if (empty($response['oauth_token']) || empty($response['oauth_verifier']) || !$session->getData('oauth_token_secret')) {
            return false;
        }

        $oauthToken = $response['oauth_token'];
        $oauthVerifier = $response['oauth_verifier'];
        $oauthTokenSecret = $session->getData('oauth_token_secret');

        $oauthBaseText = "GET&";
        $oauthBaseText .= urlencode(self::URL_ACCESS_TOKEN)."&";
        $oauthBaseText .= urlencode("oauth_consumer_key=".$this->_applicationId."&");
        $oauthBaseText .= urlencode("oauth_nonce=".$oauthNonce."&");
        $oauthBaseText .= urlencode("oauth_signature_method=HMAC-SHA1&");
        $oauthBaseText .= urlencode("oauth_token=".$oauthToken."&");
        $oauthBaseText .= urlencode("oauth_timestamp=".$oauthTimestamp."&");
        $oauthBaseText .= urlencode("oauth_verifier=".$oauthVerifier."&");
        $oauthBaseText .= urlencode("oauth_version=1.0");


        $key = $this->_secret .'&'. $oauthTokenSecret;
        $oauthSignature = base64_encode(hash_hmac('sha1', $oauthBaseText, $key, true));

        $url = self::URL_ACCESS_TOKEN;
        $url .= '?oauth_nonce='.$oauthNonce;
        $url .= '&oauth_signature_method=HMAC-SHA1';
        $url .= '&oauth_timestamp='.$oauthTimestamp;
        $url .= '&oauth_consumer_key='.$this->_applicationId;
        $url .= '&oauth_token='.urlencode($oauthToken);
        $url .= '&oauth_verifier='.urlencode($oauthVerifier);
        $url .= '&oauth_signature='.urlencode($oauthSignature);
        $url .= '&oauth_version=1.0';

        $result = null;
        if ($response = $this->_call($url)) {
            parse_str($response, $result);
        }

        $this->_setLog($result);

        // Get user data.
        if (!empty($result['oauth_token']) && !empty($result['oauth_token_secret'])) {
            $oauthNonce = md5(uniqid(rand(), true));
            $oauthTimestamp = time();

            $oauthToken = $result['oauth_token'];
            $oauthTokenSecret = $result['oauth_token_secret'];
            $screenName = $result['screen_name'];

            $oauthBaseText = "GET&";
            $oauthBaseText .= urlencode(self::URL_ACCOUNT_DATA).'&';
            $oauthBaseText .= urlencode("include_email=true&");
            $oauthBaseText .= urlencode('oauth_consumer_key='.$this->_applicationId.'&');
            $oauthBaseText .= urlencode('oauth_nonce='.$oauthNonce.'&');
            $oauthBaseText .= urlencode('oauth_signature_method=HMAC-SHA1&');
            $oauthBaseText .= urlencode('oauth_timestamp='.$oauthTimestamp."&");
            $oauthBaseText .= urlencode('oauth_token='.$oauthToken."&");
            $oauthBaseText .= urlencode('oauth_version=1.0&');
            $oauthBaseText .= urlencode('screen_name=' . $screenName);

            $key = $this->_secret .'&'. $oauthTokenSecret;
            $signature = base64_encode(hash_hmac("sha1", $oauthBaseText, $key, true));

            $url = self::URL_ACCOUNT_DATA;
            $url .= "?include_email=true";
            $url .= '&oauth_consumer_key=' . $this->_applicationId;
            $url .= '&oauth_nonce=' . $oauthNonce;
            $url .= '&oauth_signature=' . urlencode($signature);
            $url .= '&oauth_signature_method=HMAC-SHA1';
            $url .= '&oauth_timestamp=' . $oauthTimestamp;
            $url .= '&oauth_token=' . urlencode($oauthToken);
            $url .= '&oauth_version=1.0';
            $url .= '&screen_name=' . $screenName;

            $data = array();
            if ($response = $this->_call($url)) {
                $data = json_decode($response, true);
            }

            $this->_setLog($data, true);
        }

        if (!$this->_userData = $this->_prepareData($data)) {
            return false;
        }

        $this->_setLog($this->_userData, true);

        return true;
    }

    // Step 1.
    protected function _getStartToken()
    {
        $result = null;

        $oauthNonce = md5(uniqid(rand(), true));
        $oauthTimestamp = time();

        $oauthBaseText = "GET&";
        $oauthBaseText .= urlencode(self::URL_REQUEST_TOKEN)."&";
        $oauthBaseText .= urlencode("oauth_callback=".urlencode($this->_redirectUri)."&");
        $oauthBaseText .= urlencode("oauth_consumer_key=".$this->_applicationId."&");
        $oauthBaseText .= urlencode("oauth_nonce=".$oauthNonce."&");
        $oauthBaseText .= urlencode("oauth_signature_method=HMAC-SHA1&");
        $oauthBaseText .= urlencode("oauth_timestamp=".$oauthTimestamp."&");
        $oauthBaseText .= urlencode("oauth_version=1.0");

        $key = $this->_secret."&";
        $oauthSignature = base64_encode(hash_hmac("sha1", $oauthBaseText, $key, true));

        $url = self::URL_REQUEST_TOKEN;
        $url .= '?oauth_callback='.urlencode($this->_redirectUri);
        $url .= '&oauth_consumer_key='.$this->_applicationId;
        $url .= '&oauth_nonce='.$oauthNonce;
        $url .= '&oauth_signature='.urlencode($oauthSignature);
        $url .= '&oauth_signature_method=HMAC-SHA1';
        $url .= '&oauth_timestamp='.$oauthTimestamp;
        $url .= '&oauth_version=1.0';

        if ($response = $this->_call($url)) {
            parse_str($response, $result);
        }

        if (!empty($result['oauth_token_secret'])) {
            $session = Mage::getSingleton('customer/session');
            // $_SESSION['oauth_token'] = $result['oauth_token'];
            $session->setData('oauth_token_secret', $result['oauth_token_secret']);
        }

        $this->_setLog($response);

        return $result;
    }

    protected function _prepareData($data)
    {
        if (empty($data['id'])) {
            return false;
        }

        if (!empty($data['name'])) {
            $nameParts = explode(' ', $data['name'], 2);
            $data['name'] = $nameParts[0];
            $data['name2'] = !empty($nameParts[1])? $nameParts[1] : '';
        }

        return parent::_prepareData($data);
    }

    public function getSocialUrl()
    {
        if ($this->getUserId()) {
            return 'https://twitter.com/intent/user?user_id=' . $this->getUserId();
        }

        return null;
    }

}
