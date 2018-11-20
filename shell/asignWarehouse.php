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

            echo $email . "->" . $incrementId . "\n";

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
            Mage::throwException('Hubo un error al crear tu pedido, por favor intenta nuevamente (EWHS).');
    }

    public function getUserIdFromOrigin($email)
    {

        $query = "SELECT id_member FROM sb_member WHERE email = '{$email}'";

        $customerData = $this->connection->raw_fetchRow($query);


        if (!empty($customerData['id_member']))
            return $customerData['id_member'];
        else
            return null;
    }

}

$shell = new Mage_Shell_AsignWarehouse();
$shell->run();