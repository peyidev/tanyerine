<?php
class Sual_Forms_Block_Categories extends Mage_Core_Block_Abstract implements Mage_Widget_Block_Interface {
/**
  * Produce links list rendered as html
  *
  * @return string
  */
  protected function _toHtml() {
    $html = '<div class="subcategory-block">';
    $category = Mage::registry('current_category');
    if ($category) {
        //$html .= '<h1>'.$category->getName().'</h1>';
        $subcategories = $category->getChildrenCategories();
        $html .= '<ul>';
        foreach ($subcategories as $sub) {
          $subcategory = Mage::getModel('catalog/category')->load($sub->getId());
          if ($imgUrl = $this->getImageUrl($subcategory->getThumnail())) {
            $html .= '<li>';
            $html .= '<img src="'.$imgUrl.'" alt="'.$this->escapeHtml($subcategory->getName()).'" title="'.$this->escapeHtml($subcategory->getName()).'" />';
            $html .= '<h2>'.$subcategory->getName().'</h2>';
            $html .= '</li>';
          }
        }
        $html .= '</ul>';
    }
    $html .= "</div>";
    return $html;      
  }

  /**
     * Retrieve image URL
     *
     * @return string
     */
    public function getImageUrl($image)
    {
      $url = false;
      if ($image) {
          $url = Mage::getBaseUrl('media').'catalog/category/'.$image;
      }
      return $url;
    }
}