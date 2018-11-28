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

class Magmodules_Googleshopping_Helper_Selftest extends Magmodules_Googleshopping_Helper_Data
{

    const SUPPORT_URL = 'https://www.magmodules.eu/help/googleshopping/googleshopping-selftest-results';

    /**
     *
     */
    public function runFeedTests()
    {
        $result = array();

        /** @var Magmodules_Googleshopping_Model_Googleshopping $model */
        $model = Mage::getModel("googleshopping/googleshopping");

        $enabled = Mage::getStoreConfig('googleshopping/general/enabled');
        if ($enabled) {
            $result[] = $this->getPass('Module Enabled');
        } else {
            $result[] = $this->getFail('Module Disabled');
        }

        $moduleDisabled = $this->getUncachedConfigValue('advanced/modules_disable_output/Magmodules_Googleshopping');
        if ($moduleDisabled) {
            $result[] = $this->getFail('Module Output Disabled', '#module-output');
        }

        $local = $this->checkOldVersion('Googleshopping');
        if ($local) {
            $result[] = $this->getNotice('Old version or local overwrite detected', '#local');
        }

        $flatProduct = Mage::getStoreConfig('catalog/frontend/flat_catalog_product');
        $bypassFlat = Mage::getStoreConfig('googleshopping/generate/bypass_flat');

        if ($flatProduct) {
            if ($bypassFlat) {
                $result[] = $this->getNotice('Catalog Product Flat bypass is enabled', '#bypass');
            } else {
                $result[] = $this->getPass('Catalog Product Flat is enabled');

                $storeId = $this->getStoreIdConfig();
                $nonFlatAttributes = $this->checkFlatCatalog($model->getFeedAttributes($storeId, 'flatcheck'));

                if (!empty($nonFlatAttributes)) {
                    $atts = '<i>' . implode($nonFlatAttributes, ', ') . '</i>';
                    $url = Mage::helper("adminhtml")->getUrl('adminhtml/googleshopping/addToFlat');
                    $msg = $this->__('Missing Attribute(s) in Catalog Product Flat: %s', $atts);
                    $msg .= '<br/> ' . $this->__(
                        '<a href="%s">Add</a> attributes to Flat Catalog or enable "Bypass Flat Product Tables"',
                        $url
                    );
                    $result[] = $this->getFail($msg, '#missingattributes');
                }
            }
        } else {
            $result[] = $this->getNotice('Catalog Product Flat is disabled', '#flatcatalog');
        }

        $flatCategoy = Mage::getStoreConfig('catalog/frontend/flat_catalog_category');
        if ($flatCategoy) {
            $result[] = $this->getPass('Catalog Catagory Flat is enabled');
        } else {
            $result[] = $this->getNotice('Catalog Catagory Flat is disabled', '#flatcatalog');
        }

        if ($lastRun = $this->checkMagentoCron()) {
            if ((time() - strtotime($lastRun)) > 3600) {
                $msg = $this->__('Magento cron not seen in last hour (last: %s)', $lastRun);
                $result[] = $this->getFail($msg, '#cron');
            } else {
                $msg = $this->__('Magento cron seems to be running (last: %s)', $lastRun);
                $result[] = $this->getPass($msg);
            }
        } else {
            $result[] = $this->getFail('Magento cron not setup', '#cron');
        }

        return $result;
    }

    /**
     * @param        $msg
     * @param string $link
     *
     * @return string
     */
    public function getPass($msg, $link = null)
    {
        return $this->getHtmlResult($msg, 'pass', $link);
    }

    /**
     * @param        $msg
     * @param        $type
     * @param string $link
     *
     * @return string
     */
    public function getHtmlResult($msg, $type, $link)
    {
        $format = null;

        if ($type == 'pass') {
            $format = '<span class="googleshopping-success">%s</span>';
        }

        if ($type == 'fail') {
            $format = '<span class="googleshopping-error">%s</span>';
        }

        if ($type == 'notice') {
            $format = '<span class="googleshopping-notice">%s</span>';
        }

        if ($format) {
            if ($link) {
                $format = str_replace(
                    '</span>', '<span class="more"><a href="%s">More Info</a></span></span>',
                    $format
                );
                return sprintf($format, Mage::helper('googleshopping')->__($msg), self::SUPPORT_URL . $link);
            } else {
                return sprintf($format, Mage::helper('googleshopping')->__($msg));
            }
        }
    }

    /**
     * @param        $msg
     * @param string $link
     *
     * @return string
     */
    public function getFail($msg, $link = null)
    {
        return $this->getHtmlResult($msg, 'fail', $link);
    }

    /**
     * @param        $msg
     * @param string $link
     *
     * @return string
     */
    public function getNotice($msg, $link = null)
    {
        return $this->getHtmlResult($msg, 'notice', $link);
    }

    /**
     *
     */
    public function checkMagentoCron()
    {
        $tasks = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToSelect('finished_at')
            ->addFieldToFilter('status', 'success');

        $tasks->getSelect()
            ->limit(1)
            ->order('finished_at DESC');

        return $tasks->getFirstItem()->getFinishedAt();
    }
}
