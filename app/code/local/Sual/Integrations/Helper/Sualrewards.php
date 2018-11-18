<?php

/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 10/18/18
 * Time: 12:17 PM
 */
class Sual_Integrations_Helper_Sualrewards extends Mage_Core_Helper_Abstract
{

    public $customer;

    public function getCustomer($customerId)
    {

        if (empty($this->customer)) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $this->customer = $customer;
        }

        return $this->customer;

    }



    public function balancePoints($customerId, $orderId = '')
    {

        if(empty($orderId)){
            $sbeauty = $this->getTotalRewardsFromOrigin($customerId);
            $magento = $this->getTotalRewardsFromMagento($customerId);

            if (!$this->rewardsEqual($sbeauty, $magento)) {

                $originRewards = $this->getDetailRewardsFromOrigin($customerId);
                $magentoRewards = $this->getDetailRewardsFromMagento($customerId);
                $magentoControlKeys = array_column($magentoRewards,'rewardpoints_description');
                $magentoControlOrders = array_column($magentoRewards,'order_id');

                foreach($originRewards as $oReward){

                    if(!in_array($oReward['ctrl_entry'], $magentoControlKeys) && !in_array($oReward['ctrl_entry'], $magentoControlOrders)){
                        $this->add($customerId,$oReward['points'],1, $oReward['ctrl_entry']);
                    }

                }

            }
        }else{

            $pointsToExport = $this->getPointsFromOrder($orderId);
            $hasPoints =  $this->getPointsFromOrderOrigin($orderId);

            if(!empty($hasPoints))
                return false;

            $cont = 0;

            foreach($pointsToExport as $points){

                $this->addRemote($customerId, ($orderId . $cont), $points);
                $cont++;

            }

        }

    }


    public function testRewards()
    {
        $this->balancePoints(2);
        $this->getPointsFromOrder(100000003);
    }

    public function getDetailRewardsFromMagento($customerId)
    {
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');

        $tableName = $resource->getTableName('rewardpoints_account');

        $query = "SELECT * FROM $tableName  WHERE customer_id = '{$customerId}'";
        $magentoRewards = $readConnection->fetchAll($query);

        return $magentoRewards;
    }

    public function getDetailRewardsFromOrigin($customerId)
    {
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $customerPhone = $this->getCustomer($customerId)->getData('phone');

        if(!empty($customerPhone)){
            $detailPoints = $connection->query('SELECT * FROM sb_member_track_points WHERE id_unique_member = "' . $customerPhone . '"');
            $result = array();
            foreach ($detailPoints as $detail) {
                $result[] = $detail;
            }
        }else{
            $result = array();
        }
        return $result;
    }

    public function getTotalRewardsFromOrigin($customerId)
    {

        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $customerPhone = $this->getCustomer($customerId)->getData('phone');
        if(!empty($customerPhone)){
            $totalPoints = $connection->query('SELECT * FROM sb_member_points WHERE id_unique_member = "' . $customerPhone . '"');
            $total = 0;
            foreach ($totalPoints as $point) {
                $total = $point['points'];
                break;
            }

        }else{
            $total = 0;
        }
        return intval($total);
    }


    public function getTotalRewardsFromMagento($customerId)
    {
        return intval($this->info($customerId, 1)['current']);
    }

    public function rewardsEqual($p1, $p2)
    {
        return (abs($p1 - $p2) <= 1) ? true : false;
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

        $customer = $this->getCustomer($customerId);
        if (!$customer->getId()) {
            $this->_fault('data_invalid');
        }

        if (trim($storeId) == "") {
            $this->_fault('data_invalid');
        }


        if (Mage::getStoreConfig('rewardpoints/default/flatstats', $storeId)) {
            $reward_flat_model = Mage::getModel('rewardpoints/flatstats');
            $result['current'] = $reward_flat_model->collectPointsCurrent($customerId, $storeId);
            $result['received'] = $reward_flat_model->collectPointsReceived($customerId, $storeId);
            $result['spent'] = $reward_flat_model->collectPointsSpent($customerId, $storeId);
            $result['waiting'] = $reward_flat_model->collectPointsWaitingValidation($customerId, $storeId);
            $result['lost'] = $reward_flat_model->collectPointsLost($customerId, $storeId);
        } else {
            $reward_model = Mage::getModel('rewardpoints/stats');
            $result['current'] = $reward_model->getPointsCurrent($customerId, $storeId);
            $result['received'] = $reward_model->getRealPointsReceivedNoExpary($customerId, $storeId);
            $result['spent'] = $reward_model->getPointsSpent($customerId, $storeId);
            $result['waiting'] = $reward_model->getPointsWaitingValidation($customerId, $storeId);
            $result['lost'] = $reward_model->getRealPointsLost($customerId, $storeId);
        }

        return $result;
    }


    public function addRemote($customerId, $order, $points)
    {
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');

        $customer = Mage::getModel('customer/customer')->load($customerId);

        $customerPhone = $customer->getData('phone');

        $insertQuery = "INSERT INTO sb_member_track_points(id_unique_member, points, ctrl_entry, ctrl_state) 
                          VALUES ('{$customerPhone}', '{$points}', '{$order}', 'A')";

        $connection->query($insertQuery);
    }

    /**
     * Add points data
     *
     * @param int $customerId, $points
     * @param string $storeIds
     * @param string $originKey
     * @return boolean
     */
    public function add($customerId, $points, $storeIds, $originKey)
    {
        $customer = $this->getCustomer($customerId);

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

        $data = array(
            (($points > 0) ? 'points_current' :  'points_spent') => abs($points),
            'customer_id' => $customerId,
            'store_id' => $storeIds,
            'order_id' => J2t_Rewardpoints_Model_Stats::TYPE_POINTS_ADMIN,
            'date_start' => date("Y-m-d"),
            'rewardpoints_description' => $originKey);

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

    public function getPointsFromOrderOrigin($orderId){
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $totalPoints = $connection->query('SELECT * FROM sb_member_track_points WHERE ctrl_entry = "' . $orderId . '"');
        $total = 0;
        foreach ($totalPoints as $point) {
            $total = $point['points'];
            break;
        }

        return $total;
    }

    public function getPointsFromOrder($orderId){

        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');

        $tableName = $resource->getTableName('rewardpoints_account');

        $query = "SELECT * FROM $tableName  WHERE order_id = '{$orderId}'";
        $magentoRewards = $readConnection->fetchAll($query);
        $result = array();

        foreach($magentoRewards as $reward){
            if($reward['points_current'] != 0)
                $result[] = $reward['points_current'];
            if($reward['points_spent'] != 0)
                $result[] = $reward['points_spent'] * -1;
        }

        return $result;

    }
}