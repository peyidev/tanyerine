<?php

require_once 'abstract.php';
ini_set('display_errors', 1);


class Mage_Shell_Test_Services extends Mage_Shell_Abstract
{


    public function cotizarEnvio(){

        //dhlexpress
        //estafeta

    }

    public function run()
    {
        $helper =  Mage::helper('sual_integrations/data');
        $response = $helper->callService("mensajeria/cotizar",'{"carrier":"dhlexpress","calle":"Cerrada de la Talavera","colonia":"El Cobano","codigoPostal":"53040","municipio":"San antonio de ayala","ciudad":"Irapuato","estado":"guanajuato","pais":"Mexico"}');
        $response = $helper->callService("mensajeria/cotizar",'{"carrier":"estafeta","calle":"Cerrada de la Talavera","colonia":"El Cobano","codigoPostal":"53040","municipio":"San antonio de ayala","ciudad":"Irapuato","estado":"guanajuato","pais":"Mexico"}');

        print_r($response);
    }


}


$shell = new Mage_Shell_Test_Services();
$shell->run();
die();