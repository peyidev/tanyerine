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
}
