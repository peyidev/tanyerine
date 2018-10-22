<?php
class Sual_Integrations_Model_Observer extends Varien_Event_Observer
{
   public function importCustomer($observer)
   {
      $post = Mage::app()->getRequest()->getParam('login');
      $email = $post['username'];
      $new_db_resource = Mage::getSingleton('core/resource');
      $connection = $new_db_resource->getConnection('import_db');
      $query = "SELECT AES_DECRYPT(sb.password,'SualBeauty') as decryptedpassword, sb.* from sb_member AS sb WHERE email = \"{$email}\";";
      $customerData = $connection->raw_fetchRow($query);
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
}
