<?php

class Sual_Importer_Block_Execute_Renderer_Html
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{


    public function render(Varien_Object $row)
    {
        $value =  $row->getData($this->getColumn()->getIndex());


        return !empty($value) ? '<pre style="max-width: 200px;">'.$value.'</pre>' : "<strong style='color:#72C868;font-size: 20px; width:100%; padding:10px; text-align: center;float: left;'>EJECUTANDO</strong>";
    }
}