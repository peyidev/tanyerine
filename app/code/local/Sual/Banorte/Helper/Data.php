<?php

class Sual_Banorte_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * Converts the response string into array
     * @access public
     * @param string $string
     * @return arrya
     */
    public function getToArray($string){
        $response = array();
        $params = explode('&', $string);
        foreach ($params as $param) {
            $pair = explode('=', $param);
            $response[$pair[0]] = $pair[1];
        }
        return $response;
    }

    /**
     *
     * @access public
     * @param integer $number
     * @return string
     */
    public function justifyNumber($number){
        if(strlen((string)$number) == 1){
            return (string)'0' . $number;
        }
        return (string)$number;
    }

    /**
     *
     * @access public
     * @param type $string
     * @return string
     */
    public function replace($string){
        return str_replace('_', ' ', $string);
    }
}

