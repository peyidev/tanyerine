<?php

require_once 'abstract.php';


class Mage_Shell_AsignWarehouse extends Mage_Shell_Abstract
{

    public function run()
    {
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToFilter('id_warehouse', array('null' => true));

        foreach ($orders as $order){

            Mage::log($order->getIncrementId() . " Procesando", null, 'balanceo.log');

            $email = $order->getCustomerEmail();
            $incrementId = $order->getIncrementId();

            $originUser = $this->getUserIdFromOrigin($email);

            if(empty($originUser))
                $this->exportUser($order->getCustomerId());


            $originOrderId = $this->getOrderFromOrigin($order->getIncrementId());

            if(!empty($originOrderId['id']) && !empty($originOrderId['id_warehouse'])){

                $order->setIdWarehouse($originOrderId['id_warehouse']);
                $order->save();
                Mage::log("Caso 1, balanceando tiene shopping, tiene warehouse el origen, se inserta en magento" . $order->getIncrementId(), null, 'balanceo.log');
                continue;

            }else if(!empty($originOrderId['id_shopping']) && empty($originOrderId['id_warehouse'])){

                $warehouse = $this->setWarehouse(
                    empty($originUser) ? $this->getUserIdFromOrigin($email) : $originUser,
                    $incrementId
                );
                $order->setIdWarehouse($warehouse);
                $order->save();
                Mage::log("Caso 2, balanceando tiene shopping, no tiene warehouse el origen, se inserta en magento y se manda a sistema central" . $order->getIncrementId(), null, 'balanceo.log');
                continue;

            }else{

                $this->exportOrder($order);
                $warehouse = $this->setWarehouse(
                    empty($originUser) ? $this->getUserIdFromOrigin($email) : $originUser,
                    $incrementId
                );
                $order->setIdWarehouse($warehouse);
                $order->save();
                Mage::log("Caso 3, no hay orden, ni sistema central, se exporta la orden a ecom, se manda a sistema central" . $order->getIncrementId(),null,'balanceo.log');

                continue;
            }

        }
    }

    protected function getDob($dob){
        $dob = explode(' ',$dob)[0];
        $dob = explode('-',$dob);

        return array(
            "year" => $dob[0],
            "month" => $dob[1],
            "day" => $dob[2]
        );
    }


    public function exportOrder($order)
    {

        $new_db_resource = Mage::getSingleton('core/resource');
        $this->connection = $new_db_resource->getConnection('import_db');

        $incrementId = $order->getIncrementId();
        $items = $order->getAllVisibleItems();

        $customerSualId = $this->getUserIdFromOrigin($order->getCustomerEmail());

        $exportIdShopping = $incrementId;
        $exportIdMember = $customerSualId;
        $exportTotalQty = 0;
        $exportSubtotal = $order->getGrandTotal();

        $itemsQuery = '';

        foreach ($items as $item) {

            $exportTotalQty += $item->getQtyOrdered();

            //echo $item->getSku() .  " X " . $item->getQtyOrdered() . " $" .  $item->getPrice() .  "->";
            $exportIdProduct = $this->getProductIdFromOrigin($item->getSku());
            $exportQuantity = $item->getQtyOrdered();
            $exportPrice = $item->getPrice();

            $itemsQuery .= "INSERT INTO sb_shoppingcart_items 
                              VALUES ('{$exportIdShopping}', '{$exportIdMember}','{$exportIdProduct}','{$exportQuantity}',0,'{$exportPrice}');";
        }

        $orderQuery = "INSERT INTO sb_shoppingcart(id_shopping, id_member, quantity, subtotal, payment_status) 
                          VALUES('{$exportIdShopping}',{$exportIdMember},{$exportTotalQty},{$exportSubtotal},'MAGENTO');";

        try{
            $this->connection->query($orderQuery);
            $this->connection->query($itemsQuery);
            $order->setIdWarehouse($this->setWarehouse($exportIdMember, $exportIdShopping));
        }catch(Exception $e){
            //La orden ya fue insertada en origen o no pudo ser obtenido el warehouse
            Mage::log($e->getMessage());
        }

        return true;
    }


    protected function exportUser($idUser){

        try {

            $customer = Mage::getModel('customer/customer')->load($idUser);

            $name = $customer->getData('firstname');
            $first_name = $customer->getData('firstname');
            $last_name = $customer->getData('lastname');
            $email = $customer->getData('email');
            $password = Mage::helper('core')->decrypt($customer->getData('password'));
            $mobile = $customer->getData('phone');

            $dob = $this->getDob($customer->getData('dob'));

            $birth_day = $dob['day'];
            $birth_month = $dob['month'];
            $birth_year = $dob['year'];

            $query = "INSERT INTO sb_member ( login_as, name, first_name, last_name, email, password, mobile, birth_day, birth_month, birth_year, is_guest, ctrl_status ) " .
                "VALUES ('','{$name}','{$first_name}','{$last_name}','{$email}',AES_ENCRYPT('{$password}','SualBeauty'),'{$mobile}',{$birth_day},{$birth_month},{$birth_year}, 'N', 'A' )";

            $new_db_resource = Mage::getSingleton('core/resource');
            $connection = $new_db_resource->getConnection('import_db');
            $connection->query($query);
        } catch (Exception $e) {
            Mage::log($e->getMessage());
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

    public function getOrderFromOrigin($orderId){

        $query = " SELECT id_warehouse, sb.id_shopping AS id_shopping
                     FROM sb_shoppingcart as sb
                                JOIN sb_shoppingcart_items AS sbi ON sb.id_shopping = sbi.id_shopping
                     WHERE sb.id_shopping = '{$orderId}';";

        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');

        $orderData = $connection->raw_fetchRow($query);


        if (!empty($orderData['id_shopping']))
            return array('id' => $orderData['id_shopping'], 'id_warehouse' => $orderData['id_warehouse']);
        else
            return null;
    }

    public function getUserIdFromOrigin($email)
    {

        $query = "SELECT id_member FROM sb_member WHERE email = '{$email}'";
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');

        $customerData = $connection->raw_fetchRow($query);

        if (!empty($customerData['id_member']))
            return $customerData['id_member'];
        else
            return null;
    }

    public function getProductIdFromOrigin($sku)
    {

        $query = "SELECT id FROM sb_product WHERE sku = '{$sku}'";

        $customerData = $this->connection->raw_fetchRow($query);


        if (!empty($customerData['id']))
            return $customerData['id'];
        else
            return null;
    }

}

$shell = new Mage_Shell_AsignWarehouse();
$shell->run();