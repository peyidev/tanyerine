<?php

require_once 'abstract.php';


class Mage_Shell_AsignWarehouse extends Mage_Shell_Abstract
{

    public function run()
    {
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToFilter('id_warehouse', array('null' => true));

        foreach ($orders as $order){
            $email = $order->getCustomerEmail();
            $incrementId = $order->getIncrementId();
            $warehouse = $this->setWarehouse($this->getUserIdFromOrigin($email), $incrementId);
            echo $email . "->" . $incrementId . "->" . "\n";
            print_r($warehouse);
        }
    }

    protected function setWarehouse($exportIdMember,$exportIdShopping){

        $params = array(
            "member" => $exportIdMember,
            "cart" => $exportIdShopping
        );

        $helper = Mage::helper('sual_integrations/data');
        $response = $helper->callService("magento/confirm_shoppingcart_all_warehouse", $params);

        if(!empty($response->data->warehouse))
            return $response->data->warehouse;
        else
           return $response;
    }

    public function getUserIdFromOrigin($email)
    {

        $query = "SELECT id_member FROM sb_member WHERE email = '{$email}'";
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');

        $customerData = $connection->raw_fetchRow($query);

        print_r($customerData);

        if (!empty($customerData['id_member']))
            return $customerData['id_member'];
        else
            return null;
    }

}

$shell = new Mage_Shell_AsignWarehouse();
$shell->run();