<?php

require_once 'abstract.php';
ini_set('display_errors', 1);


class Mage_Shell_ImportProduct extends Mage_Shell_Abstract
{


    public function run()
    {
        $source = $this->getArg('source');
        if($source == "frontend"){
            $executionId = $this->getArg('executionid');
        }else{
            $source = "cron";
            $ejecucionesActivas = Mage::getModel('sual_importer/execute')->getCollection()
                ->addFieldToFilter('tipo_ejecucion', array("eq" => "import_productos"))
                ->addFieldToFilter('fin', array('null' => 1))->getFirstItem()->getData();

            if(!empty($ejecucionesActivas['importer_execute_id'])){
                die("No es posible realizar esta importación. \n Existe un proceso de importación similar en ejecución");
                return;
            }

            $params = array(
                "tipo_ejecucion" => "import_productos"
            );
            $model = Mage::getModel('sual_importer/execute');
            $model->addData($params);
            $model->save();
            $executionId = $model->getId();
        }

        $helper = Mage::helper('sual_importer/data');
        $helper->execute($executionId, $source);
    }


}


$shell = new Mage_Shell_ImportProduct();
$shell->run();