<?php
class Sual_Integrations_Model_Observer extends Varien_Event_Observer
{
   public $connection = null;
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
      
      if ($customerData && !$current->entity_id) {
         $customer->setFirstname($customerData['first_name'])
               ->setLastname($customerData['last_name'])
               ->setEmail($customerData['email'])
               ->setPhone($customerData['mobile'])
               ->setDob($customerData['birth_day']."/".$customerData['birth_month']."/".$customerData['birth_year'])
               ->setPassword($customerData['decryptedpassword']);
         try{
            $customer->save();
            //$this->importOrders($customerData);
         } catch (Exception $e) {
            Mage::log("Error creating customer on login event");
         }
      }
      return $this;
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
                    "VALUES ('','{$name}','{$first_name}','{$last_name}','{$email}',AES_ENCRYPT('{$password}','SualBeauty'),'{$mobile}',{$birth_day},{$birth_month},{$birth_year},'{$newsletter}', 'N', 'P' )";
                    
         $new_db_resource = Mage::getSingleton('core/resource');
         $connection = $new_db_resource->getConnection('import_db');
         $customerData = $connection->query($query);
      } catch (Exception $e) {
         Mage::log($e->getMessage());
      }
   }

   private function importOrders($customerData) {
      $query = "SELECT * FROM `sb_shoppingcart` WHERE `id_member` = ".$customerData['id_member']." and `subtotal` IS NOT NULL;";
      $customerOrders = $this->connection->query($query);
      foreach ($customerOrders as $key => $order) {
         $products = $this->getOrderProducts($order['id_shopping']);
         $order['products'] = $products;
         $this->createOrder($order,$customerData);
      }
   }

   private function getOrderProducts($id_shopping) 
   {
      $products = [];
      $query = "SELECT sbp.`sku` FROM `sb_shoppingcart_items` sbsi
               LEFT JOIN `sb_product` sbp on (sbp.`id` = sbsi.`id_product`)
               WHERE sbsi.`id_shopping` = \"".$id_shopping."\";";
      $result = $this->connection->query($query);
      foreach ($result as $key => $product) {
         if ($product['sku'] != 'ENVIO') {
            $products[] = $product['sku'];
         }
      }
      return $products;
   }

   private function createOrder($orderData,$customerData) 
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
      foreach($productsSku as $sku){
         $product = Mage::getModel('catalog/product')->load($sku, 'sku');
         if ($product) {
            $addedProduct++;
            $quote->addProduct($product,new Varien_Object(array('qty'   => 1)));
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
         'company' =>'', 
         'street' => array( 
            '0' => $orderData['shipping_address_1']." ".$orderData['shipping_streetname'],
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
         'company' =>'', 
         'street' => array( 
            '0' => $orderData['shipping_address_1']." ".$orderData['shipping_streetname'],
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
