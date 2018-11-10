<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 10/18/18
 * Time: 12:17 PM
 */ 
class Sual_Integrations_Helper_Data extends Mage_Core_Helper_Abstract {


    public $url = "http://34.194.215.157/sualbeauty/";

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

}