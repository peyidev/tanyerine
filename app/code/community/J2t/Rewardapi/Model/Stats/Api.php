<?php
/**
 * J2T RewardsAPI
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
 * @package    J2t_Rewardsapi
 * @copyright  Copyright (c) 2012 J2T DESIGN. (http://www.j2t-design.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Reward API
 *
 * @category   Community
 * @package    J2t_Rewardapi
 * @author     J2T Design <contact@j2t-design.net>
 */
class J2t_Rewardapi_Model_Stats_Api extends J2t_Rewardapi_Model_Api_Resource
{
    /*protected $_mapAttributes = array(
        'customer_id' => 'entity_id'
    );*/
    /**
     * Prepare data to insert/update.
     * Creating array for stdClass Object
     *
     * @param stdClass $data
     * @return array
     */
    protected function _prepareData($data)
    {
       /*foreach ($this->_mapAttributes as $attributeAlias=>$attributeCode) {
            if(isset($data[$attributeAlias]))
            {
                $data[$attributeCode] = $data[$attributeAlias];
                unset($data[$attributeAlias]);
            }
        }*/
        return $data;
    }

    /**
     * Create new point entry
     *
     * @param array $pointData
     * @return int
     */
    public function create($pointData)
    {
        
        $pointData = $this->_prepareData($pointData);
        try {
            $point = Mage::getModel('rewardpoints/stats')
                ->setData($pointData)
                ->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        return $point->getId();
    }

    /**
     * Retrieve customer points data
     *
     * @param int $customerId
     * @param int $storeId
     * @return array
     */
    public function info($customerId, $storeId)
    {
        $result = array();
        
        $customer = Mage::getModel('customer/customer')->load($customerId);        
        if (!$customer->getId()) {
            $this->_fault('data_invalid');
        }
        
        if (trim($storeId) == ""){
            $this->_fault('data_invalid');
        }
        
        
        if (Mage::getStoreConfig('rewardpoints/default/flatstats', $storeId)){
            $reward_flat_model = Mage::getModel('rewardpoints/flatstats');
            $result['current'] = $reward_flat_model->collectPointsCurrent($customerId, $storeId);
            $result['received'] = $reward_flat_model->collectPointsReceived($customerId, $storeId);
            $result['spent'] = $reward_flat_model->collectPointsSpent($customerId, $storeId);
            $result['waiting'] = $reward_flat_model->collectPointsWaitingValidation($customerId, $storeId);
            $result['lost'] = $reward_flat_model->collectPointsLost($customerId, $storeId);
        } else {
            $reward_model = Mage::getModel('rewardpoints/stats');
            $result['current'] = $reward_model->getPointsCurrent($customerId, $storeId);
            $result['received'] =  $reward_model->getRealPointsReceivedNoExpary($customerId, $storeId);
            $result['spent'] = $reward_model->getPointsSpent($customerId, $storeId);
            $result['waiting'] = $reward_model->getPointsWaitingValidation($customerId, $storeId);
            $result['lost'] = $reward_model->getRealPointsLost($customerId, $storeId);
        } 
        
        return $result;
    }

    /**
     * Retrieve cutomers points data
     *
     * @param  int $storeId
     * @param  array $filters
     * @return array
     */
    public function items($storeId, $filters)
    {
        $collection = Mage::getModel('rewardpoints/stats')->getCollection();
            //->addAttributeToSelect('*');
        //return $filters;
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    /*if (isset($this->_mapAttributes[$field])) {
                        $field = $this->_mapAttributes[$field];
                    }*/

                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }
        
        $collection->addValidPoints($storeId);
        $collection->addClientEntries();
        $collection->showCustomerInfo();
        
        

        $result = array();
        foreach ($collection as $rewardpoints) {
            $data = $rewardpoints->toArray();
            //$row  = array();

            /*foreach ($this->_mapAttributes as $attributeAlias => $attributeCode) {
                $row[$attributeAlias] = (isset($data[$attributeCode]) ? $data[$attributeCode] : null);
            }*/

            /*foreach ($this->getAllowedAttributes($customer) as $attributeCode => $attribute) {
                if (isset($data[$attributeCode])) {
                    $row[$attributeCode] = $data[$attributeCode];
                }
            }*/

            //$result[] = $row;
            $result[] = $data;
        }

        return $result;
    }

    /**
     * Add points data
     *
     * @param int $customerId, $points
     * @param string $storeIds
     * @return boolean
     */
    public function add($customerId, $points, $storeIds)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);

        if (!$customer->getId()) {
            $this->_fault('not_exists');
        }
        
        if (!$points) {
            $this->_fault('not_exists');
        }
        
        $store_array = explode(",", $storeIds);
        if ($store_array != array()){
            foreach ($store_array as $storeId){
                $current_store = Mage::getModel('core/store')->load(trim($storeId));
                if (!$current_store->getId()){
                    $this->_fault('not_exists');
                }
            }
        } else {
            $this->_fault('not_exists');
        }
        
        
        $data = array('points_current' => $points, 'customer_id' => $customerId, 'store_id' => $storeIds, 'order_id' => J2t_Rewardpoints_Model_Stats::TYPE_POINTS_ADMIN, 'date_start' => date("Y-m-d"));
        
        $model = Mage::getModel('rewardpoints/stats');
        $model->setData($data);

        $model->save();
        if ($store_array != array()){
            foreach ($store_array as $storeId){
                Mage::getModel('rewardpoints/flatstats')->processRecordFlat($customerId, $storeId);
            }
        }
        
        
        return true;
    }
    
    public function removepoints($filters)
    {
        $collection = Mage::getModel('rewardpoints/stats')->getCollection();
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }
        
        $result = array();
        foreach ($collection as $rewardpoints) {
            $model = Mage::getModel('rewardpoints/stats')->load($rewardpoints->getId());
            if ($model->getId()){
                $model->delete();
                Mage::getModel('rewardpoints/flatstats')->processRecordFlat($model->getCustomerId(), $model->getStoreId());
            } else {
                $this->_fault('not_exists');
            }
        }

        return true;
    }

    /**
     * Remove points data
     *
     * @param int $customerId, $points
     * @param string $storeIds
     * @return boolean
     */
    public function remove($customerId, $points, $storeIds)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);

        if (!$customer->getId()) {
            $this->_fault('not_exists');
        }
        
        if (!$points) {
            $this->_fault('not_exists');
        }
        
        $store_array = explode(",", $storeIds);
        if ($store_array != array()){
            foreach ($store_array as $storeId){
                $current_store = Mage::getModel('core/store')->load(trim($storeId));
                if (!$current_store->getId()){
                    $this->_fault('not_exists');
                }
            }
        } else {
            $this->_fault('not_exists');
        }
        
        
        $data = array('points_spent' => $points, 'customer_id' => $customerId, 'store_id' => $storeIds, 'order_id' => J2t_Rewardpoints_Model_Stats::TYPE_POINTS_ADMIN);
        
        $model = Mage::getModel('rewardpoints/stats');
        $model->setData($data);

        $model->save();
        if ($store_array != array()){
            foreach ($store_array as $storeId){
                Mage::getModel('rewardpoints/flatstats')->processRecordFlat($customerId, $storeId);
            }
        }
        return true;
    }

} // Class Mage_Customer_Model_Customer_Api End
