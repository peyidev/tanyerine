<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 10/18/18
 * Time: 12:17 PM
 */ 
class Sual_Integrations_Helper_Data extends Mage_Core_Helper_Abstract {


    public $url = "https://www.sualbeauty.com/";

    public function callService($type, $params){

        if(is_array($params)){
            $params = json_encode($params);
        }

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