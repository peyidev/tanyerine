<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@j2t-design.com so we can send you a copy immediately.
 *
 * @category   Magento extension
 * @package    RewardsPoint2
 * @copyright  Copyright (c) 2009 J2T DESIGN. (http://www.j2t-design.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class J2t_Rewardpoints_Block_Referral extends Mage_Core_Block_Template
{
    const XML_PATH_ADVANCED_REFERRAL_POINTS = 'rewardpoints/referral_advanced/referral_steps';
    
    public function __construct()
    {   
        parent::__construct();
        $this->setTemplate('rewardpoints/referral.phtml');
        $referred = Mage::getResourceModel('rewardpoints/referral_collection')
            ->addClientFilter(Mage::getSingleton('customer/session')->getCustomer()->getId());
        $referred->getSelect()->order('rewardpoints_referral_id DESC');
        $this->setReferred($referred);
    }

    public function _prepareLayout()
    {
        parent::_prepareLayout();
        $pager = $this->getLayout()->createBlock('page/html_pager', 'rewardpoints.referral')
            ->setCollection($this->getReferred());
        $this->setChild('pager', $pager);
        $this->getReferred()->load();

        return $this;
        //return parent::_prepareLayout();
    }

    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    public function getReferringUrl()
    {
        $userId = Mage::getSingleton('customer/session')->getCustomer()->getId();
        //return $this->getUrl('rewardpoints/index/goReferral')."referrer/$userId";
        $real_url = $this->getUrl('rewardpoints/index/goReferral', array("referrer" => $userId));
        return $this->getUrl('', array("referral-program" => str_replace('/','-',base64_encode($userId.'j2t'))));
    }

    public function isPermanentLink()
    {
        $return_val = Mage::getStoreConfig('rewardpoints/registration/referral_permanent', Mage::app()->getStore()->getId());
        return $return_val;
    }

    public function isAddthis()
    {
        if (Mage::getStoreConfig('rewardpoints/registration/referral_addthis', Mage::app()->getStore()->getId())
                && Mage::getStoreConfig('rewardpoints/registration/referral_addthis_account', Mage::app()->getStore()->getId()) != ""){
            return true;
        }
        return false;
    }
    
    
    public function getAdvancedPointValue() {
        $custom_points = Mage::getStoreConfig(self::XML_PATH_ADVANCED_REFERRAL_POINTS, Mage::app()->getStore()->getId());
        $custom_points_array = unserialize($custom_points);

        if (sizeof($custom_points_array)) {
            //Get number of valid orders
            $sentences = array();
            foreach ($custom_points_array as $custom_point) {
                if (isset($custom_point['min_order_qty']) && isset($custom_point['max_order_qty']) && isset($custom_point['point_value_referrer']) && isset($custom_point['calculation_type_referrer']) && isset($custom_point['point_value_referred']) && isset($custom_point['calculation_type_referred']) && isset($custom_point['date_from']) && isset($custom_point['date_end'])) {
                    $nowDate = date("Y-m-d");
                    $nowDate = new Zend_Date($nowDate, Varien_Date::DATE_INTERNAL_FORMAT);
                    if ($custom_point['date_from'] != "") {
                        $fromDate = new Zend_Date($custom_point['date_from'], Varien_Date::DATE_INTERNAL_FORMAT);
                        //verify if now > fromDate; retuns 0 if they are equal; 1 if this object's part was more recent than $date's part; otherwise -1.
                        if ($fromDate->compare($nowDate) === 1 || $fromDate->compare($nowDate) === 0) {
                            continue;
                        }
                    }
                    if ($custom_point['date_end'] != "") {
                        $endDate = new Zend_Date($custom_point['date_end'], Varien_Date::DATE_INTERNAL_FORMAT);
                        if ($nowDate->compare($endDate) === 1 || $nowDate->compare($endDate) === 0) {
                            continue;
                        }
                    }
                    /*if ((int) $custom_point['point_value_referred'] == 0 && !$referrer) {
                        continue;
                    }

                    if ((int) $custom_point['point_value_referrer'] == 0 && $referrer) {
                        continue;
                    }*/

                    /*if ((int) $custom_point['min_order_qty'] != 0 && (int) $custom_point['min_order_qty'] > $nb_valid_orders) {
                        continue;
                    }
                    if ((int) $custom_point['max_order_qty'] != 0 && (int) $custom_point['max_order_qty'] < $nb_valid_orders) {
                        continue;
                    }*/

                    $point_value = Mage::helper('core')->currency(Mage::helper('rewardpoints')->convertPointsToMoneyEquivalence(floor(1)), true, false);
                    $referralPointMethod = $custom_point['calculation_type_referrer'];
                    $rewardPoints = $custom_point['point_value_referrer'];
                    if ($referralPointMethod == J2t_Rewardpoints_Model_Calculationtype::RATIO_POINTS) {
                        if ($custom_point['min_order_qty'] > 0 && $custom_point['max_order_qty'] > 0){
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('From %s to %s valid order(s) placed by referred friend, your friend gets: %s points for each %s spent.', $custom_point['min_order_qty'], $custom_point['max_order_qty'], $custom_point['point_value_referred'], $point_value);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('From %s to %s valid order(s) placed by referred friend, you get: %s points for each %s spent.', $custom_point['min_order_qty'], $custom_point['max_order_qty'], $custom_point['point_value_referrer'], $point_value);
                            }
                        } else if ($custom_point['min_order_qty'] == 0 && $custom_point['max_order_qty'] > 0) {
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('Until %s valid order(s) placed by referred friend, your friend gets: %s points for each %s spent.', $custom_point['max_order_qty'], $custom_point['point_value_referrer'], $point_value);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('Until %s valid order(s) placed by referred friend, you get: %s points for each %s spent.', $custom_point['max_order_qty'], $custom_point['point_value_referred'], $point_value);
                            }
                        } else if ($custom_point['min_order_qty'] > 0 && $custom_point['max_order_qty'] == 0) {
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('From %s valid order(s) placed by referred friend, your friend gets: %s points for each %s spent.', $custom_point['min_order_qty'], $custom_point['point_value_referred'], $point_value);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('From %s valid order(s) placed by referred friend, you get: %s points for each %s spent.', $custom_point['min_order_qty'], $custom_point['point_value_referrer'], $point_value);
                            }
                        }
                    } else if ($referralPointMethod == J2t_Rewardpoints_Model_Calculationtype::CART_SUMMARY) {
                        if ($custom_point['min_order_qty'] > 0 && $custom_point['max_order_qty'] > 0){
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('From %s to %s valid order(s) placed by referred friend, your friend gets: %s points for each %s spent.', $custom_point['min_order_qty'], $custom_point['max_order_qty'], $custom_point['point_value_referred'], $point_value);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('From %s to %s valid order(s) placed by referred friend, you get: %s points for each %s spent.', $custom_point['min_order_qty'], $custom_point['max_order_qty'], $custom_point['point_value_referrer'], $point_value);
                            }
                        } else if ($custom_point['min_order_qty'] == 0 && $custom_point['max_order_qty'] > 0) {
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('Until %s valid order(s) placed by referred friend, your friend gets: %s points for each %s spent.', $custom_point['max_order_qty'], $custom_point['point_value_referred'], $point_value);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('Until %s valid order(s) placed by referred friend, you get: %s points for each %s spent.', $custom_point['max_order_qty'], $custom_point['point_value_referrer'], $point_value);
                            }
                        } else if ($custom_point['min_order_qty'] > 0 && $custom_point['max_order_qty'] == 0) {
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('From %s valid order(s) placed by referred friend, your friend gets: %s points for each %s spent.', $custom_point['min_order_qty'], $custom_point['point_value_referred'], $point_value);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('From %s valid order(s) placed by referred friend, you get: %s points for each %s spent.', $custom_point['min_order_qty'], $custom_point['point_value_referrer'], $point_value);
                            }
                        }
                    } else if ($referralPointMethod == J2t_Rewardpoints_Model_Calculationtype::STATIC_VALUE) {
                        if ($custom_point['min_order_qty'] > 0 && $custom_point['max_order_qty'] > 0){
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('From %s to %s valid order(s) placed by referred friend, your friend gets %s points for each order.', $custom_point['min_order_qty'], $custom_point['max_order_qty'], $custom_point['point_value_referred']);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('From %s to %s valid order(s) placed by referred friend, you get %s points for each order.', $custom_point['min_order_qty'], $custom_point['max_order_qty'], $custom_point['point_value_referrer']);
                            }
                        } else if ($custom_point['min_order_qty'] == 0 && $custom_point['max_order_qty'] > 0) {
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('Until %s valid order(s) placed by referred friend, your friend gets %s points for each order.', $custom_point['max_order_qty'], $custom_point['point_value_referred']);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('Until %s valid order(s) placed by referred friend, you get %s points for each order.', $custom_point['max_order_qty'], $custom_point['point_value_referrer']);
                            }
                        } else if ($custom_point['min_order_qty'] > 0 && $custom_point['max_order_qty'] == 0) {
                            if ($custom_point['point_value_referred']){
                                $sentences[] = $this->__('From %s valid order(s) placed by referred friend, your friend gets %s points for each order.', $custom_point['min_order_qty'], $custom_point['point_value_referred']);
                            }
                            if ($custom_point['point_value_referrer']){
                                $sentences[] = $this->__('From %s valid order(s) placed by referred friend, you get %s points for each order.', $custom_point['min_order_qty'], $custom_point['point_value_referrer']);
                            }
                        }
                    }
                    

                    return $sentences;
                }
            }
        }
        return 0;
    }
    
    public function getReferralPointsReceived($rewardpoints_referral_id){
        $store_id = Mage::app()->getStore()->getId();
        $customerId = Mage::getModel('customer/session')->getCustomerId();
        $reward_model = Mage::getModel('rewardpoints/stats');
        
        return $reward_model->getRealPointsReceivedNoExpiry($customerId, $store_id, true, $rewardpoints_referral_id)+0;
    }

    public function getReferrerPoints()
    {
        return Mage::getStoreConfig('rewardpoints/registration/referral_points', Mage::app()->getStore()->getId());
    }

    public function getFriendPoints()
    {
        return Mage::getStoreConfig('rewardpoints/registration/referral_child_points', Mage::app()->getStore()->getId());
    }
    
    public function getReferrerRegistrationPoints()
    {
        return Mage::getStoreConfig('rewardpoints/registration/referrer_registration_points', Mage::app()->getStore()->getId());
    }

    public function getFriendRegistrationPoints()
    {
        return Mage::getStoreConfig('rewardpoints/registration/referred_registration_points', Mage::app()->getStore()->getId());
    }
    
    public function getReferrerCalculationType()
    {
        return Mage::getStoreConfig('rewardpoints/registration/referral_points_method', Mage::app()->getStore()->getId());
    }
    
    public function getFriendCalculationType()
    {
        return Mage::getStoreConfig('rewardpoints/registration/referral_child_points_method', Mage::app()->getStore()->getId());
    }
    
    public function getMinOrderAmount()
    {
        return Mage::getStoreConfig('rewardpoints/registration/referral_min_order', Mage::app()->getStore()->getId());
    }
}