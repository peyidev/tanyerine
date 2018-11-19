<?php
class Sual_Forms_Block_Tree extends Mage_Core_Block_Abstract implements Mage_Widget_Block_Interface {
/**
  * Produce links list rendered as html
  *
  * @return string
  */
  protected function _toHtml() {
    $html = '<div class="subcategory-brands">';
    $category = Mage::registry('current_category');
    if ($category) {
        $html .= '<h1>'.__('Todas las marcas').'</h1>';
        $subcategories = $category->getChildrenCategories();
        $index = "";  
        $marcas .= '<div class="brands-content">';
        foreach ($subcategories as $sub) {
          $subcategory = Mage::getModel('catalog/category')->load($sub->getId());
          $level3 = $subcategory->getChildrenCategories();
          $marcas .= '<div class="section" id="'.$sub->getId().'">';
          $marcas .= '<div class="section-title"><strong>'.$subcategory->getName().'</strong></div>';
          $marcas .= '<div class="section-list"><ul>';
          $index .= '<a href="#'.$sub->getId().'">'.$subcategory->getName().'</a>';
          foreach ($level3 as $link) {
            $linkcate = Mage::getModel('catalog/category')->load($link->getId());
            $url = $linkcate->getUrl($linkcate);
            $marcas .= '<li><a href="'.$url.'">'.$linkcate->getName().'</a></li>';
          }
          $marcas .= "</ul></div></div>";
        }
        $marcas .= "</div>";
         $html .= '<div class="index">'.$index."</div>";
        $html .= $marcas;
    }
    $html .= "</div>";
    return $html;      
  }
}