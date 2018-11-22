<?php

class Sual_Integrations_Model_Observer extends Varien_Event_Observer
{
    public $connection = null;

    public function updateProduct($observer) {
        $product = Mage::getModel('catalog/product')->load(Mage::app()->getRequest()->getParam('product', 0));
        if ($product) {
            $stock = Mage::helper('sual_integrations/data')->getStock($product->getSku());
            //$stockItem = $product->getStockItem();
            $stockItem = Mage::getModel('cataloginventory/stock_item');
            $stockItem->assignProduct($product);

            Mage::log("Producto" . $product->getSku() . " StockReal -> " . $stock . " ActualMagento -> " . $stockItem->getQty(), null, 'stock.log');
            if ($stock != 'error' && $stockItem->getQty() != $stock) {
                $stockItem->setData('is_in_stock', ($stock > 0) ? 1 : 0);
                $stockItem->setData('stock_id', 1);
                $stockItem->setData('store_id', 1);
                $stockItem->setData('qty', $stock);
                $stockItem->save();
            }
        }
    }

    public function importCustomer($observer)
    {
        $post = Mage::app()->getRequest()->getParam('login');
        $email = $post['username'];
        $new_db_resource = Mage::getSingleton('core/resource');
        $this->connection = $new_db_resource->getConnection('import_db');
        $query = "SELECT AES_DECRYPT(sb.password,'SualBeauty') as decryptedpassword, sb.* from sb_member AS sb WHERE email = \"{$email}\";";
        $customerData = $this->connection->raw_fetchRow($query);
        $websiteId = Mage::app()->getWebsite()->getId();
        $store = Mage::app()->getStore();
        $customer = Mage::getModel("customer/customer");
        $customer->setWebsiteId($websiteId)->setStore($store);
        $current = $customer->loadByEmail($email);

        $bday = !empty($customerData['birth_day']) ? $customerData['birth_day'] : null;
        $bmonth = !empty($customerData['birth_month']) ? $customerData['birth_month'] : null;
        $byear = !empty($customerData['birth_year']) ? $customerData['birth_year'] : null;


        if ($customerData && !$current->entity_id) {
            $customer->setFirstname($customerData['first_name'])
                ->setLastname($customerData['last_name'])
                ->setEmail($customerData['email'])
                ->setPhone($customerData['mobile'])
                ->setPassword($customerData['decryptedpassword']);

            if(!empty($bday) && !empty($bmonth) && !empty($byear))
                $customer->setDob($customerData['birth_day'] . "/" . $customerData['birth_month'] . "/" . $customerData['birth_year']);

            try {
                $customer->save();
                //$this->importOrders($customerData);
            } catch (Exception $e) {
                Mage::log("Error creating customer on login event");
            }
        }
        return $this;
    }

    public function exportRewards($observer){

        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        $customerId = $order->getCustomerId();

        $helper = Mage::helper('sual_integrations/sualrewards');
        $helper->balancePoints($customerId, $order->getIncrementId());
    }

    public function importRewards($observer)
    {
        $customer = $observer->getCustomer();
        if ($customer) {
            Mage::helper('sual_integrations/sualrewards')->balancePoints($customer->getEntityId());
        }
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

    public function getProductIdFromOrigin($sku)
    {

        $query = "SELECT id FROM sb_product WHERE sku = '{$sku}'";

        $customerData = $this->connection->raw_fetchRow($query);


        if (!empty($customerData['id']))
            return $customerData['id'];
        else
            return null;
    }

    public function exportOrder($observer)
    {

        $new_db_resource = Mage::getSingleton('core/resource');
        $this->connection = $new_db_resource->getConnection('import_db');

        $order = $observer->getEvent()->getOrder();
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

        return $observer;
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

    public function verifyStock($observer){

        $new_db_resource = Mage::getSingleton('core/resource');
        $this->connection = $new_db_resource->getConnection('import_db');

        $order = $observer->getEvent()->getOrder();
        $items = $order->getAllVisibleItems();
        $itemsArray = array();
        $qtyOrder = array();

        foreach($items as $item){
            $itemsArray[] = $item->getSku();
            $qtyOrder[$item->getSku()]['stock'] = $item->getQtyOrdered();
            $qtyOrder[$item->getSku()]['name'] = $item->getName();
        }

        $itemsString = implode(',',$itemsArray);

        $stockQuery = "SELECT sku, available FROM sb_product WHERE sku IN({$itemsString})";
        $stockData = $this->connection->fetchAll($stockQuery);
        $errorProducts = array();

        foreach($stockData as $stock){
            if($qtyOrder[$stock['sku']]['stock'] > $stock['available']){
                $errorProducts[] = $qtyOrder[$stock['sku']]['name'];
            }
        }
        if(!empty($errorProducts)){
            Mage::throwException('Algunos productos ya no se encuentran en inventario. (' . implode(',', $errorProducts) . ')');
        }
    }

    public function exportCustomer($observer)
    {
        $this->add_member();
    }

    public function add_member($customer)
    {
        try {
            $resquest = Mage::app()->getRequest();
            $login_as = $resquest->getParam('email');
            $name = $resquest->getParam('firstname');
            $first_name = $resquest->getParam('firstname');
            $last_name = $resquest->getParam('lastname');
            $last_name = $resquest->getParam('lastname');
            $email = $resquest->getParam('email');
            $password = $resquest->getParam('password');
            $mobile = $resquest->getParam('phone');
            $password = $resquest->getParam('password');
            $birth_day = $resquest->getParam('day');
            $birth_month = $resquest->getParam('month');
            $birth_year = $resquest->getParam('year');
            $newsletter = $resquest->getParam('is_subscribed');

            $query = "INSERT INTO sb_member ( login_as, name, first_name, last_name, email, password, mobile, birth_day, birth_month, birth_year, newsletter, is_guest, ctrl_status ) " .
                "VALUES ('','{$name}','{$first_name}','{$last_name}','{$email}',AES_ENCRYPT('{$password}','SualBeauty'),'{$mobile}',{$birth_day},{$birth_month},{$birth_year},'{$newsletter}', 'N', 'A' )";

            $new_db_resource = Mage::getSingleton('core/resource');
            $connection = $new_db_resource->getConnection('import_db');
            $customerData = $connection->query($query);
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }

    private function importOrders($customerData)
    {
        $query = "SELECT * FROM `sb_shoppingcart` WHERE `id_member` = " . $customerData['id_member'] . " and `subtotal` IS NOT NULL;";
        $customerOrders = $this->connection->query($query);
        foreach ($customerOrders as $key => $order) {
            $products = $this->getOrderProducts($order['id_shopping']);
            $order['products'] = $products;
            $this->createOrder($order, $customerData);
        }
    }

    private function getOrderProducts($id_shopping)
    {
        $products = [];
        $query = "SELECT sbp.`sku` FROM `sb_shoppingcart_items` sbsi
               LEFT JOIN `sb_product` sbp on (sbp.`id` = sbsi.`id_product`)
               WHERE sbsi.`id_shopping` = \"" . $id_shopping . "\";";
        $result = $this->connection->query($query);
        foreach ($result as $key => $product) {
            if ($product['sku'] != 'ENVIO') {
                $products[] = $product['sku'];
            }
        }
        return $products;
    }

    private function createOrder($orderData, $customerData)
    {

        $websiteId = Mage::app()->getWebsite()->getId();
        $store = Mage::app()->getStore();
        $quote = Mage::getModel('sales/quote')->setStoreId($store->getId());

        // Set Sales Order Quote Currency
        //$quote->setCurrency($order->AdjustmentAmount->currencyID);
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId)
            ->loadByEmail($customerData['email']);


        // Assign Customer To Sales Order Quote
        $quote->assignCustomer($customer);

        // Configure Notification
        $quote->setSendCconfirmation(0);
        $productsSku = $orderData['products'];
        $addedProduct = 0;
        foreach ($productsSku as $sku) {
            $product = Mage::getModel('catalog/product')->load($sku, 'sku');
            if ($product) {
                $addedProduct++;
                $quote->addProduct($product, new Varien_Object(array('qty' => 1)));
            }
        }
        if ($addedProduct == 0) {
            return false;
        }

        // Set Sales Order Billing Address
        $billingAddress = $quote->getBillingAddress()->addData(array(
            'customer_address_id' => '',
            'prefix' => '',
            'firstname' => $orderData['shipping_firstname'],
            'middlename' => '',
            'lastname' => $orderData['shipping_lastname'],
            'suffix' => '',
            'company' => '',
            'street' => array(
                '0' => $orderData['shipping_address_1'] . " " . $orderData['shipping_streetname'],
                '1' => $orderData['shipping_streetnumber_1']
            ),
            'city' => $orderData['shipping_lastname'],
            'country_id' => 'MX',
            'region' => 'UP',
            'postcode' => $orderData['shipping_lastname'],
            'telephone' => $orderData['shipping_phone'],
            'fax' => '',
            'vat_id' => '',
            'save_in_address_book' => 0
        ));

        // Set Sales Order Shipping Address
        $shippingAddress = $quote->getShippingAddress()->addData(array(
            'customer_address_id' => '',
            'prefix' => '',
            'firstname' => $orderData['shipping_firstname'],
            'middlename' => '',
            'lastname' => $orderData['shipping_lastname'],
            'suffix' => '',
            'company' => '',
            'street' => array(
                '0' => $orderData['shipping_address_1'] . " " . $orderData['shipping_streetname'],
                '1' => $orderData['shipping_streetnumber_1']
            ),
            'city' => $orderData['shipping_lastname'],
            'country_id' => 'MX',
            'region' => 'UP',
            'postcode' => $orderData['shipping_lastname'],
            'telephone' => $orderData['shipping_phone'],
            'fax' => '',
            'vat_id' => '',
            'save_in_address_book' => 0
        ));

        //if($shipprice==0){
        //$shipmethod = 'freeshipping_freeshipping';
        //}

        // Collect Rates and Set Shipping & Payment Method
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('freeshipping_freeshipping')
            ->setPaymentMethod('checkmo');

        // Set Sales Order Payment
        $quote->getPayment()->importData(array('method' => 'checkmo'));

        // Collect Totals & Save Quote
        $quote->collectTotals()->save();

        // Create Order From Quote
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        //$increment_id = $service->getOrder()->getRealOrderId();

        // Resource Clean-Up
        //$quote = $customer = $service = null;

        // Finished
        // /return $increment_id;
    }
}
