<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 10/18/18
 * Time: 12:17 PM
 */ 
class Sual_Integrations_Helper_Data extends Mage_Core_Helper_Abstract {


    public $url = "https://www.sualbeauty.mx/";

    public function callService($type, $params){

        $handle = curl_init();

        $url = $this->url . $type;

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $params);

        $output = curl_exec($handle);

        curl_close($handle);

        @$result = json_decode($output);

        return !empty($result) ? $result : null;

    }

    public function getStock($sku){

        $url = "http://login.sualbeauty.com/webhook/getStock";
        $ch = curl_init( $url );

        $params = array("sku" => $sku);

        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $params ) );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:text/json'));
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        $result = curl_exec($ch);

        @$json = json_decode( $result );

        return !empty($json->ok) ? $json->data : 'error';
    }

}