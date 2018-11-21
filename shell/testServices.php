<?php

require_once 'abstract.php';
ini_set('display_errors', 1);


class Mage_Shell_Test_Services extends Mage_Shell_Abstract
{


    public function cotizarEnvio()
    {

        //dhlexpress
        //estafeta

    }

    public function run()
    {

        $customer = Mage::getModel('customer/customer')->load(6);

        print_r($customer->getData());

//        $helper = Mage::helper('sual_integrations/sualrewards');
//        $helper->balancePoints(2,100000004);
        //$helper = Mage::helper('sual_integrations/data');
//        $response = $helper->callService("mensajeria/cotizar",'{"carrier":"dhlexpress","calle":"Cerrada de la Talavera","colonia":"El Cobano","codigoPostal":"53040","municipio":"San antonio de ayala","ciudad":"Irapuato","estado":"guanajuato","pais":"Mexico"}');
//        $response = $helper->callService("mensajeria/cotizar",'{"carrier":"estafeta","calle":"Cerrada de la Talavera","colonia":"El Cobano","codigoPostal":"53040","municipio":"San antonio de ayala","ciudad":"Irapuato","estado":"guanajuato","pais":"Mexico"}');

//        print_r($response);

        //QPARAM EXAMPLE
        //BillTo_firstName=Pedro&BillTo_lastName=Laris&BillTo_street=Cerrada+de+la+Talavera&BillTo_streetNumber=19&BillTo_streetNumber2=&BillTo_street2Col=El+Cobano&BillTo_street2Del=San+antonio+de+ayala&BillTo_city=Irapuato&BillTo_state=guanajuato&BillTo_country=MX&BillTo_phoneNumber=5537228701&BillTo_postalCode=36600&BillTo_email=peyi.god%40gmail.com&Card_accountNumber=5454545454545454&Card_cardType=002&Card_expirationMonth=12&Card_expirationYear=2020&PurchaseTotals_grandTotalAmount=10&DeviceFingerprintID=10012040&Card_cardCCV=329&Card_cardPromotion=0
//
//        $params = array();
//        $params['BillTo_firstName'] = "Pedro";
//        $params['BillTo_lastName'] = "Laris";
//        $params['BillTo_street'] = "Cerrada+de+la+Talavera";
//        $params['BillTo_streetNumber'] = "19a";
//        $params['BillTo_streetNumber2'] = " ";
//        $params['BillTo_street2Col'] = "Colonia";
//        $params['BillTo_street2Del'] = "Delegacion+No+Se";
//        $params['BillTo_city'] = "Ciudad";
//        $params['BillTo_state'] = "Estado";
//        $params['BillTo_country'] = "MX";
//        $params['BillTo_phoneNumber'] = "1231231231";
//        $params['BillTo_postalCode'] = "53040";
//        $params['BillTo_email'] = "CORREO@CORREO.COM";
//        $params['Card_accountNumber'] = "5454545454545454";
//        //002 PARA MASTERCARD - 001 PARA VISA
//        $params['Card_cardType'] = "002";
//        $params['Card_expirationMonth'] = "12";
//        $params['Card_expirationYear'] = "2020";
//        $params['Card_cardCCV'] = "123";
//        $params['PurchaseTotals_grandTotalAmount'] = "100";
//        //0 3 6
//        $params['Card_cardPromotion'] = "0";
//        $params['DeviceFingerprintID'] = rand(10000000, 19999999);
//

//        //member=1802&cart=100000004
//        $params = array(
//            "member" => 1802,
//            "cart" => '100000004'
//        );
//        $response = $helper->callService("magento/confirm_shoppingcart_all_warehouse", $params);
//        print_r($response->data->warehouse);

        //$helper->testRewards();

       //echo  Mage::helper('sual_integrations/data')->getStock('773602504596');


    }


}


$shell = new Mage_Shell_Test_Services();
$shell->run();
die();