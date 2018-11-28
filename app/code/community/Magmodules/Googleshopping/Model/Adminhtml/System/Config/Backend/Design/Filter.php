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

class Magmodules_Googleshopping_Model_Adminhtml_System_Config_Backend_Design_Filter
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
                $value = $this->orderData($value, 'attribute');
                foreach ($value as $key => $field) {
                    if (!empty($field['attribute']) && !empty($field['condition'])) {
                        $attribute = Mage::getModel('eav/entity_attribute')
                            ->loadByCode('catalog_product', $field['attribute']);
                        $value[$key]['attribute'] = $field['attribute'];
                        $value[$key]['condition'] = $field['condition'];
                        $value[$key]['product_type'] = $field['product_type'];
                        $value[$key]['value'] = $field['value'];
                        $value[$key]['type'] = $attribute->getFrontendInput();
                    } else {
                        unset($value[$key]);
                    }
                }

                $keys = array();
                for ($i = 0; $i < count($value); $i++) {
                    $keys[] = 'filter_' . uniqid();
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