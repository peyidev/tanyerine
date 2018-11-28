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

class Magmodules_Googleshopping_Model_Googleshopping extends Magmodules_Googleshopping_Model_Common
{

    /**
     * @var Magmodules_Googleshopping_Helper_Data
     */
    public $helper;
    /**
     * @var Mage_Tax_Helper_Data
     */
    public $taxHelper;
    /**
     * @var Mage_Core_Model_Config
     */
    public $config;

    /**
     * Magmodules_Googleshopping_Model_Googleshopping constructor.
     */
    public function __construct()
    {
        $this->helper = Mage::helper('googleshopping');
        $this->taxHelper = Mage::helper('tax');
        $this->config = Mage::getModel('core/config');
    }

    /**
     * @param string $type
     * @param null   $storeId
     * @param bool   $return
     *
     * @return array|null
     */
    public function runScheduled($type = 'cron', $storeId = null, $return = false)
    {
        $returnValue = null;
        $enabled = $this->helper->getConfigData('general/enabled');
        $cron = $this->helper->getConfigData('generate/cron');
        $timeStart = microtime(true);

        if ($enabled && $cron) {
            if ($storeId == null) {
                $nextStore = $this->helper->getUncachedConfigValue('googleshopping/generate/cron_next');
                $storeIds = $this->helper->getStoreIds('googleshopping/generate/enabled');
                if (!count($storeIds)) {
                    return $returnValue;
                }

                if (empty($nextStore) || ($nextStore >= count($storeIds))) {
                    $nextStore = 0;
                }

                $storeId = $storeIds[$nextStore];
                $nextStore++;
            }

            try {
                /** @var Mage_Core_Model_App_Emulation $appEmulation */
                $appEmulation = Mage::getSingleton('core/app_emulation');
                $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                if ($result = $this->generateFeed($storeId)) {
                    $this->updateConfig($result, $type, $timeStart, $storeId);
                }

                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                $returnValue = ($return) ? $result : null;
            } catch (\Exception $e) {
                $this->helper->addToLog('runScheduled', $e->getMessage(), null, true);
            }

            if (!empty($nextStore)) {
                $this->config->saveConfig('googleshopping/generate/cron_next', $nextStore, 'default', 0);
            }
        }

        return $returnValue;
    }

    /**
     * @param        $storeId
     * @param string $type
     *
     * @return array
     * @throws Mage_Core_Exception
     * @throws Exception
     */
    public function generateFeed($storeId, $type = 'xml')
    {
        $timeStart = microtime(true);
        $this->setMemoryLimit($storeId);
        $config = $this->getFeedConfig($storeId, $type);
        $io = $this->helper->createFeed($config);
        $products = $this->getProducts($config);

        $totalCount = $this->getCollectionCountWithFilters($products);
        if ($config['limit'] > 0 && $type != 'preview') {
            $pages = ceil($totalCount / $config['limit']);
        } else {
            $pages = 1;
        }

        $curPage = 1;
        $processed = 0;

        do {
            if ($pages > 1) {
                $products->getSelect()->limitPage($curPage, $config['limit']);
            }
            $products->load();
            $parentRelations = $this->helper->getParentsFromCollection($products, $config);
            $parents = $this->getParents($parentRelations, $config);
            $prices = $this->helper->getTypePrices($config, $parents);
            $parentAttributes = $this->helper->getConfigurableAttributesAsArray($parents, $config);
            $processed += $this->getFeedData(
                $products, $parents, $config, $parentAttributes, $prices, $io,
                $parentRelations
            );

            if ($config['debug_memory'] && ($type != 'preview')) {
                $this->helper->addLog($curPage, $pages, $processed);
            }

            $products->clear();
            $parents = null;
            $prices = null;
            $parentAttributes = null;
            $curPage++;
        } while ($curPage <= $pages);

        $footer = $this->getFeedFooter($config, $timeStart, $processed, $pages);

        $feedStats = array();
        $feedStats['qty'] = $processed;
        $feedStats['date'] = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
        $feedStats['url'] = $config['file_url'];
        $feedStats['shop'] = $config['store_code'];
        $feedStats['pages'] = $pages;

        $this->helper->closeFeed($io, $config, $footer);

        return $feedStats;
    }

    /**
     * @param $storeId
     */
    public function setMemoryLimit($storeId)
    {
        if ($this->helper->getConfigData('generate/overwrite', $storeId)) {
            if ($memoryLimit = $this->helper->getConfigData('generate/memory_limit', $storeId)) {
                ini_set('memory_limit', $memoryLimit);
            }

            if ($maxExecutionTime = $this->helper->getConfigData('generate/max_execution_time', $storeId)) {
                ini_set('max_execution_time', $maxExecutionTime);
            }
        }
    }

    /**
     * @param        $storeId
     * @param string $type
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getFeedConfig($storeId, $type = 'xml')
    {
        $config = array();

        /** @var  Mage_Core_Model_Store $store */
        $store = Mage::getModel('core/store')->load($storeId);
        /** @var  Mage_Core_Model_Website $website */
        $website = Mage::getModel('core/website')->load($store->getWebsiteId());
        $websiteId = $website->getId();
        /** @var Mage_Eav_Model_Resource_Entity_Attribute $attribute */
        $attribute = Mage::getResourceModel('eav/entity_attribute');

        $config['store_id'] = $storeId;
        $config['website_id'] = $websiteId;
        $config['store_code'] = $store->getCode();
        $config['website_name'] = $this->helper->cleanData($website->getName(), 'striptags');
        $config['website_url'] = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        $config['media_url'] = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $config['media_image_url'] = $config['media_url'] . 'catalog' . DS . 'product';
        $config['media_attributes'] = $this->helper->getMediaAttributes();
        $config['media_gallery_id'] = $attribute->getIdByCode('catalog_product', 'media_gallery');
        $config['file_name_temp'] = $this->getFileName('googleshopping', $storeId, $type, true);
        $config['file_name'] = $this->getFileName('googleshopping', $storeId, $type);
        $config['file_path'] = Mage::getBaseDir() . DS . 'media' . DS . 'googleshopping';
        $config['file_url'] = $config['media_url'] . 'googleshopping' . DS . $config['file_name'];
        $config['filters'] = $this->helper->getSerializedConfigData('filter/advanced', $storeId);
        $config['version'] = (string)Mage::getConfig()->getNode()->modules->Magmodules_Googleshopping->version;
        $config['product_url_suffix'] = $this->helper->getProductUrlSuffix($storeId);
        $config['filter_enabled'] = $this->helper->getConfigData('filter/category_enabled', $storeId);
        $config['filter_cat'] = $this->helper->getConfigData('filter/categories', $storeId);
        $config['filter_type'] = $this->helper->getConfigData('filter/category_type', $storeId);
        $config['filter_status'] = $this->helper->getConfigData('filter/visibility_inc', $storeId);
        $config['category_default'] = $this->helper->getConfigData('data/category_fixed', $storeId);
        $config['producttype'] = $this->helper->getConfigData('advanced/producttype', $storeId);
        $config['identifier'] = $this->helper->getConfigData('advanced/identifier', $storeId);
        $config['stock'] = $this->helper->getConfigData('filter/stock', $storeId);
        $config['conf_enabled'] = $this->helper->getConfigData('advanced/conf_enabled', $storeId);
        $config['conf_fields'] = $this->helper->getConfigData('advanced/conf_fields', $storeId);
        $config['conf_switch_urls'] = $this->helper->getConfigData('advanced/conf_switch_urls', $storeId);
        $config['simple_price'] = $this->helper->getConfigData('advanced/simple_price', $storeId);
        $config['url_suffix'] = $this->helper->getConfigData('advanced/url_utm', $storeId);
        $config['images'] = $this->helper->getConfigData('data/images', $storeId);
        $config['image1'] = $this->helper->getConfigData('data/image1', $storeId);
        $config['condition_default'] = $this->helper->getConfigData('data/condition_default', $storeId);
        $config['stock_manage'] = Mage::getStoreConfig('cataloginventory/item_options/manage_stock');
        $config['stock_instock'] = 'in stock';
        $config['stock_outofstock'] = 'out of stock';
        $config['condition_default'] = $this->helper->getConfigData('data/condition_default', $storeId);
        $config['hide_no_stock'] = $this->helper->getConfigData('filter/stock', $storeId);
        $config['weight'] = $this->helper->getConfigData('advanced/weight', $storeId);
        $config['weight_units'] = $this->helper->getConfigData('advanced/weight_units', $storeId);
        $config['price_scope'] = Mage::getStoreConfig('catalog/price/scope');
        $config['price_add_tax'] = $this->helper->getConfigData('advanced/add_tax', $storeId);
        $config['price_add_tax_perc'] = $this->helper->getConfigData('advanced/tax_percentage', $storeId);
        $config['price_grouped'] = $this->helper->getConfigData('advanced/grouped_price', $storeId);
        $config['force_tax'] = $this->helper->getConfigData('advanced/force_tax', $storeId);
        $config['currency'] = $store->getDefaultCurrencyCode();
        $config['base_currency_code'] = $store->getBaseCurrencyCode();
        $config['use_currency'] = true;
        $config['markup'] = $this->helper->getPriceMarkup($config);
        $config['use_tax'] = $this->helper->getTaxUsage($config);
        $config['shipping'] = $this->helper->getSerializedConfigData('advanced/shipping', $storeId);
        $config['bypass_flat'] = $this->helper->getConfigData('generate/bypass_flat', $storeId);
        $config['debug_memory'] = $this->helper->getConfigData('generate/debug_memory', $storeId);
        $config['inventory'] = $this->getInventoryData();

        $eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
        if ($eavAttribute->getIdByCode('catalog_category', 'googleshopping_category')) {
            $config['category_custom'] = 'googleshopping_category';
        }

        if ($eavAttribute->getIdByCode('catalog_category', 'googleshopping_exclude')) {
            $config['category_exclude'] = 'googleshopping_exclude';
        }

        if ($eavAttribute->getIdByCode('catalog_product', 'googleshopping_exclude')) {
            $config['filter_exclude'] = 'googleshopping_exclude';
        }

        if ($this->helper->getConfigData('data/condition_type', $storeId) == 'attribute') {
            $config['condition_attribute'] = $this->helper->getConfigData('data/condition', $storeId);
        }

        if ($this->helper->getConfigData('data/name', $storeId) == 'use_custom') {
            $config['custom_name'] = $this->helper->getConfigData('data/name_custom', $storeId);
        }

        $paging = $this->helper->getConfigData('generate/paging', $storeId);
        $limit = $this->helper->getConfigData('generate/limit', $storeId);
        if ($paging && $limit > 0) {
            $config['limit'] = preg_replace('/\D/', '', $limit);
        } else {
            $config['limit'] = null;
        }

        if ($type == 'preview') {
            $config['limit'] = 200;
        }

        $config['conf_exclude_parent'] = $this->helper->getConfigData('advanced/conf_enabled', $storeId) ? 0 : 1;
        if ($config['conf_exclude_parent'] == 0) {
            $config['conf_exclude_parent'] = $this->helper->getConfigData(
                'advanced/configurable_parents',
                $storeId
            ) ? 0 : 1;
        }

        $config['field'] = $this->getFeedAttributes($storeId, $type, $config);
        $config['parent_att'] = $this->getParentAttributeSelection($config['field']);
        $config['root_category_id'] = $store->getRootCategoryId();
        $config['category_data'] = $this->helper->getCategoryData($config, $storeId);

        return $config;
    }

    /**
     * @param      $feed
     * @param      $storeId
     * @param null $type
     * @param bool $temp
     *
     * @return mixed|string
     */
    public function getFileName($feed, $storeId, $type = null, $temp = false)
    {
        if (!$fileName = Mage::getStoreConfig($feed . '/generate/filename', $storeId)) {
            $fileName = $feed . '.xml';
        }

        if (substr($fileName, -3) != 'xml') {
            $fileName = $fileName . '-' . $storeId . '.xml';
        } else {
            $fileName = substr($fileName, 0, -4) . '-' . $storeId . '.xml';
        }

        if ($type == 'preview') {
            $fileName = str_replace('.xml', '-preview.xml', $fileName);
        }

        if ($temp) {
            $fileName = time() . '-' . $fileName;
        }

        return $fileName;
    }

    /**
     * @return array
     */
    public function getInventoryData()
    {
        $invAtt = array();
        $invAtt['attributes'][] = 'qty';
        $invAtt['attributes'][] = 'is_in_stock';
        $invAtt['attributes'][] = 'use_config_manage_stock';
        $invAtt['attributes'][] = 'use_config_qty_increments';
        $invAtt['attributes'][] = 'enable_qty_increments';
        $invAtt['attributes'][] = 'use_config_enable_qty_inc';
        $invAtt['attributes'][] = 'use_config_min_sale_qty';
        $invAtt['attributes'][] = 'backorders';
        $invAtt['attributes'][] = 'use_config_backorders';
        $invAtt['attributes'][] = 'manage_stock';
        $invAtt['config_backorders'] = Mage::getStoreConfig('cataloginventory/item_options/backorders');
        $invAtt['config_manage_stock'] = Mage::getStoreConfig('cataloginventory/item_options/manage_stock');
        $invAtt['config_qty_increments'] = Mage::getStoreConfig('cataloginventory/item_options/qty_increments');
        $invAtt['config_enable_qty_inc'] = Mage::getStoreConfig('cataloginventory/item_options/enable_qty_increments');
        $invAtt['config_min_sale_qty'] = Mage::getStoreConfig('cataloginventory/item_options/min_qty');

        return $invAtt;
    }

    /**
     * @param int    $storeId
     * @param string $type
     * @param string $config
     *
     * @return mixed
     */
    public function getFeedAttributes($storeId = 0, $type = 'xml', $config = '')
    {
        $attributes = array();
        $attributes['id'] = array(
            'label'                     => 'g:id',
            'source'                    => $this->helper->getConfigData('data/id', $storeId),
            'xpath'                     => 'googleshopping/data/id',
            'parent_selection_disabled' => 1,
        );
        $attributes['title'] = array(
            'label'  => 'g:title',
            'source' => $this->helper->getConfigData('data/name', $storeId),
            'action' => 'striptags_truncate150_uppercheck',
            'xpath'  => 'googleshopping/data/name',
        );
        $attributes['description'] = array(
            'label'  => 'g:description',
            'source' => $this->helper->getConfigData('data/description', $storeId),
            'action' => 'striptags_truncate',
            'xpath'  => 'googleshopping/data/description',
        );
        $attributes['gtin'] = array(
            'label'  => 'g:gtin',
            'source' => $this->helper->getConfigData('data/gtin', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/gtin',
        );
        $attributes['brand'] = array(
            'label'  => 'g:brand',
            'source' => $this->helper->getConfigData('data/brand', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/brand',
        );
        $attributes['mpn'] = array(
            'label'  => 'g:mpn',
            'source' => $this->helper->getConfigData('data/mpn', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/mpn',
        );
        $attributes['color'] = array(
            'label'  => 'g:color',
            'source' => $this->helper->getConfigData('data/color', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/color',
        );
        $attributes['material'] = array(
            'label'  => 'g:material',
            'source' => $this->helper->getConfigData('data/material', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/material',
        );
        $attributes['pattern'] = array(
            'label'  => 'g:pattern',
            'source' => $this->helper->getConfigData('data/pattern', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/pattern',
        );
        $attributes['size'] = array(
            'label'  => 'g:size',
            'source' => $this->helper->getConfigData('data/size', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/size',
        );
        $attributes['size_type'] = array(
            'label'  => 'g:size_type',
            'source' => $this->helper->getConfigData('data/size_type', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/size_type',
        );
        $attributes['size_system'] = array(
            'label'  => 'g:size_system',
            'source' => $this->helper->getConfigData('data/size_system', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/size_system',
        );
        $attributes['gender'] = array(
            'label'  => 'g:gender',
            'source' => $this->helper->getConfigData('data/gender', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/gender',
        );
        $attributes['agegroup'] = array(
            'label'  => 'g:age_group',
            'source' => $this->helper->getConfigData('data/agegroup', $storeId),
            'action' => 'striptags',
            'xpath'  => 'googleshopping/data/agegroup',
        );
        $attributes['product_url'] = array(
            'label'  => 'g:link',
            'source' => 'product_url'
        );
        $attributes['image_link'] = array(
            'label'                     => 'g:image_link',
            'source'                    => $this->helper->getConfigData('data/image1', $storeId),
            'parent_selection_disabled' => 1,
        );
        $attributes['availability'] = array(
            'label'  => 'g:availability',
            'source' => ''
        );
        $attributes['condition'] = array(
            'label'  => 'g:condition',
            'source' => $this->helper->getConfigData('data/condition', $storeId)
        );
        $attributes['price'] = array(
            'label'                     => 'g:price',
            'collection'                => 'price',
            'parent_selection_disabled' => 1,
        );
        $attributes['weight'] = array(
            'label'  => 'g:weight',
            'source' => ''
        );
        $attributes['categories'] = array(
            'label'  => 'categories',
            'source' => ''
        );
        $attributes['bundle'] = array(
            'label'  => 'g:is_bundle',
            'source' => ''
        );
        $attributes['parent_id'] = array(
            'label'  => 'g:item_group_id',
            'source' => $this->helper->getConfigData('data/id', $storeId),
            'parent' => 2
        );
        $attributes['googleshopping_exclude'] = array(
            'label'                     => 'g:exclude',
            'source'                    => 'googleshopping_exclude',
            'parent_selection_disabled' => 1,
        );
        $attributes['google_category'] = array(
            'label'                     => 'g:google_product_category',
            'source'                    => 'googleshopping_category',
            'parent_selection_disabled' => 1,
        );

        if ($extraFields = $this->helper->getSerializedConfigData('advanced/extra', $storeId)) {
            foreach ($extraFields as $extraField) {
                $attributes['extrafields-' . $extraField['attribute']] = array(
                    'label'  => $extraField['name'],
                    'source' => $extraField['attribute'],
                    'action' => $extraField['action']
                );
            }
        }

        if ($type == 'flatcheck') {
            if ($filters = $this->helper->getSerializedConfigData('filter/advanced', $storeId)) {
                foreach ($filters as $filter) {
                    $attributes[$filter['attribute']] = array(
                        'label'  => $filter['attribute'],
                        'source' => $filter['attribute']
                    );
                }
            }

            if ($this->helper->getConfigData('data/name', $storeId) == 'use_custom') {
                $customValues = $this->helper->getConfigData('data/name_custom', $storeId);
                preg_match_all("/{{([^}]*)}}/", $customValues, $foundAtts);
                if (!empty($foundAtts)) {
                    foreach ($foundAtts[1] as $att) {
                        $attributes[$att] = array('label' => $att, 'source' => $att);
                    }
                }
            }

            $attributes['image'] = array('label' => 'Base Image', 'source' => 'image');
        }

        if ($type != 'config') {
            $attributes = $this->addAttributeActions($attributes, $storeId);
            return $this->helper->addAttributeData($attributes, $config);
        } else {
            return $attributes;
        }
    }

    /**
     * @param $attributes
     * @param $storeId
     *
     * @return mixed
     */
    public function addAttributeActions($attributes, $storeId)
    {
        foreach ($attributes as $key => $attribute) {
            if (!isset($attribute['source']) || !isset($attribute['xpath'])) {
                continue;
            }

            if ($attribute['source'] == 'mm-actions-conditional') {
                if ($condition = $this->parseConditionalField($attribute['xpath'], $storeId)) {
                    $attributes[$key] = array_merge($attributes[$key], $condition);
                }
            }

            if ($attribute['source'] == 'mm-actions-custom') {
                if ($custom = $this->parseCustomField($attribute['xpath'], $storeId)) {
                    $attributes[$key] = array_merge($attributes[$key], $custom);
                }
            }
        }

        return $attributes;
    }

    /**
     * @param $xpath
     * @param $storeId
     *
     * @return array|bool
     */
    public function parseConditionalField($xpath, $storeId)
    {
        $xpath .= '_conditional';
        $condition = Mage::getStoreConfig($xpath, $storeId);

        if (!$condition) {
            return false;
        }

        $condSplit = preg_split("/[?:]+/", str_replace(array('(', ')'), '', $condition));
        if (count($condSplit) == 3) {
            preg_match_all("/{{([^}]*)}}/", $condition, $foundAtts);
            return array(
                'conditional' => array(
                    '*:' . trim($condSplit[2]),
                    trim($condSplit[0]) . ':' . trim($condSplit[1]),
                ),
                'multi'       => implode(',', array_unique($foundAtts[1]))
            );
        }

        return false;
    }

    /**
     * @param $xpath
     * @param $storeId
     *
     * @return array|bool
     */
    public function parseCustomField($xpath, $storeId)
    {
        $xpath .= '_custom';
        $custom = Mage::getStoreConfig($xpath, $storeId);

        if (!$custom) {
            return false;
        }

        preg_match_all("/{{([^}]*)}}/", $custom, $foundAtts);
        if (isset($foundAtts[1]) && count($foundAtts[1]) > 0) {
            return array(
                'custom' => $custom,
                'multi'  => implode(',', array_unique($foundAtts[1]))
            );
        } else {
            return array(
                'custom' => $custom,
            );
        }
    }

    /**
     * @param $products
     * @param $parents
     * @param $config
     * @param $parentAttributes
     * @param $prices
     * @param $io
     * @param $parentRelations
     *
     * @return int
     */
    public function getFeedData($products, $parents, $config, $parentAttributes, $prices, $io, $parentRelations)
    {
        $qty = 0;
        foreach ($products as $product) {
            $parent = null;
            if (!empty($parentRelations[$product->getEntityId()])) {
                foreach ($parentRelations[$product->getEntityId()] as $parentId) {
                    if ($parent = $parents->getItemById($parentId)) {
                        continue;
                    }
                }
            }

            $productData = $this->helper->getProductDataRow($product, $config, $parent, $parentAttributes);
            if ($productData) {
                $productRow = array();
                foreach ($productData as $key => $value) {
                    if (!is_array($value)) {
                        $productRow[$key] = $value;
                    }
                }

                if ($extraData = $this->getExtraDataFields($productData, $config, $product, $prices, $parent)) {
                    $productRow = array_merge($productRow, $extraData);
                }

                $productRow = $this->processUnset($productRow);

                $productRow = new Varien_Object($productRow);
                Mage::dispatchEvent(
                    'googleshopping_before_write',
                    array('feed_data' => $productRow, 'product' => $product)
                );
                $this->helper->writeRow($productRow->getData(), $io);

                $productRow = null;
                $qty++;
            }
        }

        return $qty;
    }

    /**
     * @param $productData
     * @param $config
     * @param $product
     * @param $prices
     * @param $parent
     *
     * @return array
     */
    protected function getExtraDataFields($productData, $config, $product, $prices, $parent)
    {
        $_extra = array();

        if ($_custom = $this->getCustomData($config, $product)) {
            $_extra = array_merge($_extra, $_custom);
        }

        if ($_identifierExists = $this->getIdentifierExists($productData, $config)) {
            $_extra = array_merge($_extra, $_identifierExists);
        }

        if ($_categoryData = $this->getCategoryData($productData, $config)) {
            $_extra = array_merge($_extra, $_categoryData);
        }

        if (!isset($productData['g:price']['final_price_clean'])) {
            $productData['g:price'] = array();
            $price = '';
        } else {
            $price = $productData['g:price']['final_price_clean'];
        }

        $currency = !empty($config['hide_currency']) ? null : ' ' . $config['currency'];
        $itemGroupId = !empty($parent) ? $parent->getEntityId() : null;

        if ($_prices = $this->getPrices($productData['g:price'], $prices, $product, $config, $itemGroupId)) {
            $_extra = array_merge($_extra, $_prices);
            $price = str_replace($currency, '', $_prices['g:price']);
        }

        if ($_shipping = $this->getShipping($price, $config)) {
            $_extra = array_merge($_extra, $_shipping);
        }

        if ($_images = $this->getImages($productData, $config)) {
            $_extra = array_merge($_extra, $_images);
        }

        if ($_promotion = $this->getPromotion($productData)) {
            $_extra = array_merge($_extra, $_promotion);
        }

        return $_extra;
    }

    /**
     * @param                            $config
     * @param Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    protected function getCustomData($config, $product)
    {
        $custom = array();
        if (isset($config['custom_name'])) {
            $custom['g:title'] = $this->reformatString($config['custom_name'], $product, '');
        }

        return $custom;
    }

    /**
     * @param                                   $data
     * @param        Mage_Catalog_Model_Product $product
     * @param string                            $symbol
     *
     * @return string
     */
    protected function reformatString($data, $product, $symbol = '')
    {
        preg_match_all("/{{([^}]*)}}/", $data, $attributes);
        if (!empty($attributes)) {
            foreach ($attributes[0] as $key => $value) {
                if (!empty($product[$attributes[1][$key]])) {
                    if ($product->getAttributeText($attributes[1][$key])) {
                        $data = str_replace($value, $product->getAttributeText($attributes[1][$key]), $data);
                    } else {
                        $data = str_replace($value, $product[$attributes[1][$key]], $data);
                    }
                } else {
                    $data = str_replace($value, '', $data);
                }

                if ($symbol) {
                    $data = preg_replace(
                        '/' . $symbol . '+/', ' ' . $symbol . ' ',
                        rtrim(str_replace(' ' . $symbol . ' ', $symbol, $data), $symbol)
                    );
                }
            }
        }

        return trim($data);
    }

    /**
     * @param $productData
     * @param $config
     *
     * @return mixed
     */
    protected function getIdentifierExists($productData, $config)
    {
        if ($config['identifier'] == 1) {
            $identifiers = false;
            if (!empty($productData['g:gtin'])) {
                if (!empty($productData['g:brand'])) {
                    $identifiers = true;
                }
            }

            if (!empty($productData['g:mpn'])) {
                if (!empty($productData['g:brand'])) {
                    $identifiers = true;
                }
            }

            if (!$identifiers) {
                $identifier['g:identifier_exists'] = 'FALSE';
                return $identifier;
            }
        }

        if ($config['identifier'] == 2) {
            $identifier['g:identifier_exists'] = 'FALSE';
            return $identifier;
        }
    }

    /**
     * @param $productData
     * @param $config
     *
     * @return array
     */
    protected function getCategoryData($productData, $config)
    {
        $level1 = $level2 = '';
        $category = array();

        $category['g:google_product_category'] = $this->helper->cleanData($config['category_default'], 'striptags');

        if (!empty($productData['categories'])) {
            foreach ($productData['categories'] as $cat) {
                if ($cat['level'] > $level1) {
                    if (!empty($cat['custom'])) {
                        $category['g:google_product_category'] = $cat['custom'];
                        $level1 = $cat['level'];
                    }
                }
            }

            if (!empty($config['producttype'])) {
                foreach ($productData['categories'] as $cat) {
                    if ($cat['level'] > $level2) {
                        $category['g:product_type'] = implode(' > ', $cat['path']);
                        $level2 = $cat['level'];
                    }
                }
            }
        }

        if (!empty($productData['g:google_product_category']) && isset($category['g:google_product_category'])) {
            unset($category['g:google_product_category']);
        }

        return $category;
    }

    /**
     * @param                            $data
     * @param                            $confPrices
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     * @param                            $parentId
     *
     * @return array
     */
    public function getPrices($data, $confPrices, $product, $config, $parentId)
    {
        $prices = array();
        $id = $product->getEntityId();
        $parentPriceIndex = $parentId . '_' . $id;
        if ($parentId && !empty($confPrices[$parentPriceIndex])) {
            $confPrice = $this->taxHelper->getPrice($product, $confPrices[$parentPriceIndex], true);
            $confPriceReg = $this->taxHelper->getPrice($product, $confPrices[$parentPriceIndex . '_reg'], true);
            if ($confPriceReg > $confPrice) {
                $prices['g:sale_price'] = $this->helper->formatPrice($confPrice, $config);
                $prices['g:price'] = $this->helper->formatPrice($confPriceReg, $config);
            } else {
                $prices['g:price'] = $this->helper->formatPrice($confPrice, $config);
            }
        } else {
            if (isset($data['sales_price'])) {
                $prices['g:price'] = $data['regular_price'];
                $prices['g:sale_price'] = $data['sales_price'];
                if (isset($data['sales_date_start']) && isset($data['sales_date_end'])) {
                    $prices['g:sale_price_effective_date'] = str_replace(
                            ' ', 'T',
                            $data['sales_date_start']
                        ) . '/' . str_replace(' ', 'T', $data['sales_date_end']);
                }
            } else {
                $prices['g:price'] = isset($data['price']) ? $data['price'] : '';
            }
        }

        return $prices;
    }

    /**
     * @param $price
     * @param $config
     *
     * @return array
     */
    protected function getShipping($price, $config)
    {
        $shippingArray = array();
        $i = 1;
        if (!empty($config['shipping'])) {
            foreach ($config['shipping'] as $shipping) {
                if (($price >= $shipping['price_from']) && ($price <= $shipping['price_to'])) {
                    $shippingPrice = $shipping['price'];
                    $shippingPrice = number_format($shippingPrice, 2, '.', '') . ' ' . $config['currency'];
                    $shippingArrayR['g:country'] = $shipping['country'];
                    $shippingArrayR['g:service'] = $shipping['service'];
                    $shippingArrayR['g:price'] = $shippingPrice;
                    $shippingArray['g:shipping' . $i] = $shippingArrayR;
                    $i++;
                }
            }
        }

        return $shippingArray;
    }

    /**
     * @param $productData
     * @param $config
     *
     * @return array
     */
    public function getImages($productData, $config)
    {
        $_images = array();

        $i = 1;
        if ($config['images'] == 'all') {
            if (!empty($config['image1'])) {
                if (!empty($productData['image'][$config['image1']])) {
                    $_images['g:image_link'] = $productData['image'][$config['image1']];
                }
            } else {
                if (!empty($productData['image']['base'])) {
                    $_images['g:image_link'] = $productData['image']['base'];
                }
            }

            if (empty($_images['g:image_link']) && isset($productData['g:image_link'])) {
                $_images['g:image_link'] = $productData['g:image_link'];
            }

            if (!empty($productData['image']['all'])) {
                foreach ($productData['image']['all'] as $image) {
                    if (!in_array($image, $_images)) {
                        $_images['g:additional_image_link' . $i] = $image;
                        if ($i == 10) {
                            break;
                        }

                        $i++;
                    }
                }
            }
        } else {
            if (isset($productData['g:image_link'])) {
                $_images['g:image_link'] = $productData['g:image_link'];
            }
        }

        return $_images;
    }

    /**
     * @param $productData
     *
     * @return array
     */
    public function getPromotion($productData)
    {
        $_promos = array();
        $i = 1;
        if (!empty($productData['promotion_id'])) {
            $promos = explode(',', $productData['promotion_id']);
            foreach ($promos as $promo) {
                $_promos['g:promotion_id' . $i] = $promo;
                $i++;
            }
        }

        if (!empty($productData['g:promotion_id'])) {
            $promos = explode(',', $productData['g:promotion_id']);
            foreach ($promos as $promo) {
                $_promos['g:promotion_id' . $i] = $promo;
                $i++;
            }
        }

        return $_promos;
    }

    /**
     * @param $productRow
     *
     * @return mixed
     */
    public function processUnset($productRow)
    {
        if (isset($productRow['g:promotion_id'])) {
            unset($productRow['g:promotion_id']);
        }

        if (isset($productRow['promotion_id'])) {
            unset($productRow['promotion_id']);
        }

        if (isset($productRow['g:image_link'])) {
            if (isset($productRow['image_link'])) {
                unset($productRow['image_link']);
            }
        }

        if (isset($productRow['g:exclude'])) {
            unset($productRow['g:exclude']);
        }

        return $productRow;
    }

    /**
     * @param     $config
     * @param     $timeStart
     * @param int $processed
     * @param int $pages
     *
     * @return array
     */
    protected function getFeedFooter($config, $timeStart, $processed = 0, $pages = 1)
    {
        $footer = array();
        $footer['system'] = 'Magento';
        $footer['extension'] = 'Magmodules_Googleshopping';
        $footer['extension_version'] = $config['version'];
        $footer['store'] = $config['website_name'];
        $footer['url'] = $config['website_url'];

        if ($config['limit'] > 0) {
            $footer['paging'] = $config['limit'];
        }

        $footer['processed'] = $processed;
        $footer['pages'] = $pages;
        $footer['generated'] = Mage::getModel('core/date')->date('Y-m-d H:i:s');
        $footer['processing_time'] = number_format((microtime(true) - $timeStart), 4);
        return $footer;
    }

    /**
     * @param $result
     * @param $type
     * @param $timeStart
     * @param $storeId
     */
    public function updateConfig($result, $type, $timeStart, $storeId)
    {
        $html = sprintf(
            '<a href="%s" target="_blank">%s</a><br/><small>On: %s (%s) - Products: %s/%s - Time: %s</small>',
            $result['url'],
            $result['url'],
            $result['date'],
            $type,
            $result['qty'],
            $result['pages'],
            $this->helper->getTimeUsage($timeStart)
        );

        $this->config->saveConfig('googleshopping/generate/feed_result', $html, 'stores', $storeId);

        if ($this->helper->getConfigData('generate/log_generation', $storeId)) {
            $msg = strip_tags(str_replace('<br/>', ' => ', $html));
            $this->helper->addToLog('Feed Generation Store ID ' . $storeId, $msg, null, true);
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $parent
     *
     * @return mixed
     */
    public function getSuperAtts($parent)
    {
        if ($parent->getTypeId() == 'configurable') {
            return $parent->getTypeInstance(true)->getConfigurableAttributesAsArray($parent);
        }
    }

}