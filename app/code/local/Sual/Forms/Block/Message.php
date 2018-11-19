<?php
class Sual_Forms_Block_Message extends Mage_Core_Block_Template  {
  public function getContent()
  {
    $active = Mage::getStoreConfig('general/message/active');
    if ($active) {
      $pages = $active = Mage::getStoreConfig('general/message/pages');
      $content = $active = Mage::getStoreConfig('general/message/content');
      if ($pages == 'index' && Mage::getBlockSingleton('page/html_header')->getIsHomePage()) {
        return $content;
      } else if ($pages == 'all'){
        return $content;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }
}