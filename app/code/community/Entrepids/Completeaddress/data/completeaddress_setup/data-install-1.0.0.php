<?php
/**
* @category   Entrepids
* @package    Entrepids_Completeaddress
* @author     miguel.perez@entrepids.com
* @website    http://www.entrepids.com
*/
$data = array();
$countryId = 'MX';
$fileName = 'mx_locations.csv';
$dir = Mage::getBaseDir('app') . DS . 'code'.DS.'community'.DS.'Entrepids'.DS.'Completeaddress'.DS.'data'.DS.'completeaddress_setup'.DS;
$openStream = fopen($dir.$fileName, 'r');

if ($openStream !== FALSE) {
    while ($content = fgetcsv($openStream, 1000, ',')) {
        if(sizeof($content > 3)){
            $temp = array('cp' => intval($content[0]), 'neighborhood' => utf8_encode($content[1]));
            $regi = utf8_encode($content[3]);
            $municip = utf8_encode($content[2]);
            $data[$regi][$municip][] = $temp;
        }
    }
}
//Limpiamos data previa de Mexico
$dataCollection = Mage::getModel('directory/region')->getCollection()->addFieldToFilter('country_id','MX');
foreach($dataCollection as $d){
    $d->delete();
}
//data construida insertamos en la base de datos
$resource = Mage::getSingleton('core/resource');
$writeConnection = $resource->getConnection('core_write');
$table = $resource->getTableName('completeaddress/neighborhood');

foreach ($data as $state => $region) {
    $regionData = array('country_id' => $countryId, 'code' => $state, 'default_name' => $state);
    $regionId = Mage::getModel('directory/region')->setData($regionData)->save()->getId();
    foreach ($region as $municipality => $neighborhood) {
        $nbData = array();
        foreach ($neighborhood as $data) {
            $nbData[] = array('postal_code'=>$data['cp'],'region_id'=>$regionId,'neighborhood'=>$data['neighborhood'],'municipality'=>$municipality);
        }
        $writeConnection->insertMultiple($table,$nbData);
    }
}
$writeConnection->closeConnection();