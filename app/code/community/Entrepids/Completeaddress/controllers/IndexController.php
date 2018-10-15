<?php
/**
* @category   Entrepids
* @package    Entrepids_Completeaddress
* @author     miguel.perez@entrepids.com
* @website    http://www.entrepids.com
*/
class Entrepids_Completeaddress_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(){
        header('location:'.Mage::getBaseUrl());
    }
    
    public function findAddressAction(){
        //Variable para almacenar el resultado del proceso
        $result = array();
        $result['data'] = array();
        $zipcode = $this->getRequest()->getParam('zipcode');
        $zipcode = intval($zipcode);
        if(!empty($zipcode)){ //Validamos zipcode
            //consultamos zipcodes en DB
            $model = Mage::getModel('completeaddress/neighborhood');
            $zps = $model->getCollection()->addFieldToFilter('postal_code',$zipcode)->
            join( 'regions', 'regions.region_id = main_table.region_id', array('default_name'));
            foreach ($zps as $zp){
                $result['data'][] = $zp->getData();
            }
            $result['status'] = TRUE;
            $result['results'] = count($result['data']);
        }else{
            $result['status'] = FALSE;
            $result['msg'] = 'Error. Zipcode parameter is required.';
        }
        //Imprimimos resultados
        $json = json_encode($result);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($json);
    }
}