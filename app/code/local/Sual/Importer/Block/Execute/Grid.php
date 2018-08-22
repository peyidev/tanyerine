<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 8/19/18
 * Time: 4:55 PM
 */
class Sual_Importer_Block_Execute_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct()
    {
        parent::__construct();
        $this->setId('grid_id');
        // $this->setDefaultSort('COLUMN_ID');
        $this->setDefaultDir('asc');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('sual_importer/execute')->getCollection()->setOrder('importer_execute_id','desc');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {


        $this->addColumn('importer_execute_id',
            array(
                'header'=> $this->__('Id de ejecución'),
                'width' => '50px',
                'index' => 'importer_execute_id'
            )
        );

        $this->addColumn('tipo_ejecucion',
            array(
                'header'=> $this->__('Tipo de ejecución'),
                'width' => '50px',
                'index' => 'tipo_ejecucion'
            )
        );


        $this->addColumn('inicio',
            array(
                'header'=> $this->__('Inicio'),
                'width' => '50px',
                'index' => 'inicio'
            )
        );

        $this->addColumn('fin',
            array(
                'header'=> $this->__('Fin'),
                'width' => '50px',
                'index' => 'fin'
            )
        );

        $this->addColumn('fin',
            array(
                'header'=> $this->__('Fin'),
                'width' => '50px',
                'index' => 'fin'
            )
        );

        $this->addColumn('resumen',
            array(
                'header'=> $this->__('Resumen de ejecución'),
                'width' => '50px',
                'index' => 'resumen',
                'renderer' => 'Sual_Importer_Block_Execute_Renderer_Html'

            )
        );



        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
       return $this->getUrl('*/*/edit', array('id' => $row->getId()));
    }

    }
