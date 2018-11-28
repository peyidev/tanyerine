<?php
/**
 * Magmodules.eu - http://www.magmodules.eu.
 *
 * NOTICE OF LICENSE
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://www.magmodules.eu/MM-LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@magmodules.eu so we can send you a copy immediately.
 *
 * @category      Magmodules
 * @package       Magmodules_Googleshopping
 * @author        Magmodules <info@magmodules.eu>
 * @copyright     Copyright (c) 2018 (http://www.magmodules.eu)
 * @license       https://www.magmodules.eu/terms.html  Single Service License
 */

class Magmodules_Googleshopping_Model_System_Config_Backend_Design_Shipping 
    extends Mage_Adminhtml_Model_System_Config_Backend_Serialized_Array
{

    /**
     *
     */
    protected function _beforeSave()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            unset($value['__empty']);
            if (count($value)) {
                $keys = array();
                for ($i = 0; $i < count($value); $i++) {
                    $keys[] = 'fields_' . uniqid();
                }

                foreach ($value as $key => $field) {
                    $priceFrom = str_replace(',', '.', $field['price_from']);
                    $priceTo = str_replace(',', '.', $field['price_to']);
                    $price = str_replace(',', '.', $field['price']);

                    if (!$priceFrom) {
                        $priceFrom = '0.00';
                    }

                    if (!$priceTo) {
                        $priceTo = '0.00';
                    }

                    if (!$price) {
                        $price = '0.00';
                    }

                    $value[$key]['price_from'] = number_format($priceFrom, 2, '.', '');
                    $value[$key]['price_to'] = number_format($priceTo, 2, '.', '');
                    $value[$key]['price'] = number_format($price, 2, '.', '');
                }

                $value = array_combine($keys, array_values($value));
            }
        }

        $this->setValue($value);
        parent::_beforeSave();
    }

}