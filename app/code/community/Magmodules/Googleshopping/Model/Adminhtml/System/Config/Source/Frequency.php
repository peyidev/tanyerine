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

class Magmodules_Googleshopping_Model_Adminhtml_System_Config_Source_Frequency
{

    const CRON_DAILY = 'D';
    const CRON_WEEKLY = 'W';
    const CRON_MONTHLY = 'M';
    const CRON_CUSTOM = 'C';

    /**
     * Options array
     *
     * @var array
     */
    public $options = null;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $this->options = array(
                array('label' => Mage::helper('adminhtml')->__('Daily'), 'value' => self::CRON_DAILY),
                array('label' => Mage::helper('adminhtml')->__('Weekly'), 'value' => self::CRON_WEEKLY),
                array('label' => Mage::helper('adminhtml')->__('Monthly'), 'value' => self::CRON_MONTHLY),
                array('label' => Mage::helper('adminhtml')->__('-- Custom'), 'value' => self::CRON_CUSTOM)
            );
        }

        return $this->options;
    }

}