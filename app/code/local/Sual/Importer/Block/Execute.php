<?php

/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 8/19/18
 * Time: 4:55 PM
 */
class Sual_Importer_Block_Execute extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'sual_importer';
        $this->_controller = 'execute';
        $this->_headerText      = $this->__('Historial de ejecuciones');
        // $this->_addButtonLabel  = $this->__('Add Button Label');
        parent::__construct();
    }

    public function getCreateUrl()
    {
        return $this->getUrl('*/*/new');
    }

}

