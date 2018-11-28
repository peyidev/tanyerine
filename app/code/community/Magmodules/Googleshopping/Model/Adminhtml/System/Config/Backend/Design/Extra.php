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

class Magmodules_Googleshopping_Model_Adminhtml_System_Config_Backend_Design_Extra
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
                $value = $this->orderData($value, 'name');
                $keys = array();
                for ($i = 0; $i < count($value); $i++) {
                    $keys[] = 'fields_' . uniqid();
                }

                foreach ($value as $key => $field) {
                    $attribute = Mage::getModel('eav/entity_attribute')
                        ->loadByCode('catalog_product', $field['attribute']);
                    $name = str_replace(" ", "_", trim($field['name']));
                    $value[$key]['name'] = strtolower($name);
                    $value[$key]['attribute'] = $field['attribute'];
                    $value[$key]['action'] = $field['action'];
                    $value[$key]['type'] = $attribute->getFrontendInput();
                }

                $value = array_combine($keys, array_values($value));
            }
        }

        $this->setValue($value);
        parent::_beforeSave();
    }

    /**
     * @param $data
     * @param $sort
     *
     * @return mixed
     */
    public function orderData($data, $sort)
    {
        $code = "return strnatcmp(\$a['$sort'], \$b['$sort']);";
        usort($data, create_function('$a,$b', $code));
        return $data;
    }

}