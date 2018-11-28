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

class Magmodules_Googleshopping_Block_Adminhtml_System_Config_Form_Field_Feeds
    extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $helper = Mage::helper('googleshopping');
        $storeIds = $helper->getStoreIds('googleshopping/generate/enabled');
        $htmlFeedlinks = '';
        foreach ($storeIds as $storeId) {
            $generateUrl = $this->getUrl('*/googleshopping/generateManual/store_id/' . $storeId);
            $previewUrl = $this->getUrl('*/googleshopping/preview/store_id/' . $storeId);
            $downloadUrl = $this->getUrl('*/googleshopping/download/store_id/' . $storeId);
            $feedText = $helper->getUncachedConfigValue('googleshopping/generate/feed_result', $storeId);
            if (empty($feedText)) {
                $feedText = Mage::helper('googleshopping')->__('No active feed found');
                $downloadUrl = '';
            }

            $store = Mage::getModel('core/store')->load($storeId);
            $storeTitle = $store->getName();
            $storeCode = $store->getCode();

            $htmlFeedlinks .= '<tr>
             <td valign="top">' . $storeTitle . '<br/><small>Code: ' . $storeCode . '</small></td>
             <td>' . $feedText . '</td>
             <td>
              » <a href="' . $generateUrl . '">' . Mage::helper('googleshopping')->__('Generate New') . '</a><br/>
              » <a href="' . $previewUrl . '">' . Mage::helper('googleshopping')->__('Preview 100') . '</a><br/>
              » <a href="' . $downloadUrl . '">' . Mage::helper('googleshopping')->__('Download Last') . '</a>              
             </td>
            </tr>';
        }

        if (empty($htmlFeedlinks)) {
            $htmlFeedlinks = Mage::helper('googleshopping')->__('No enabled feed(s) found');
        } else {
            $htmlHeader = '<div class="grid">
             <table cellpadding="0" cellspacing="0" class="border" style="width: 100%">
              <tbody>
               <tr class="headings"><th>Store</th><th>Feed</th><th>Generate</th></tr>';
            $htmlFooter = '</tbody></table></div>';
            $htmlFeedlinks = $htmlHeader . $htmlFeedlinks . $htmlFooter;
        }

        return sprintf(
            '<tr id="row_%s"><td colspan="6" class="label" style="margin-bottom: 10px;">%s</td></tr>',
            $element->getHtmlId(),
            $htmlFeedlinks
        );
    }

}
