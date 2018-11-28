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
 * @package       Magmodules_Beslist
 * @author        Magmodules <info@magmodules.eu>
 * @copyright     Copyright (c) 2018 (http://www.magmodules.eu)
 * @license       https://www.magmodules.eu/terms.html  Single Service License
 */

class Magmodules_Googleshopping_Helper_Data extends Magmodules_Googleshopping_Helper_Write
{

    const LOG_FILENAME = 'googleshopping.log';
    const FORCE_LOG = true;

    /**
     * @param $path
     * @param $storeId
     *
     * @return mixed
     */
    public function getSerializedConfigData($path, $storeId = null)
    {
        return @unserialize($this->getConfigData($path, $storeId));
    }

    /**
     * @param $path
     * @param $storeId
     *
     * @return mixed
     */
    public function getConfigData($path, $storeId = null)
    {
        return Mage::getStoreConfig('googleshopping/' . $path, $storeId);
    }

    /**
     * @param $path
     *
     * @return array
     */
    public function getStoreIds($path)
    {
        $storeIds = array();
        /** @var Mage_Core_Model_Resource_Store_Collection $stores */
        $stores = Mage::getModel('core/store')->getCollection();
        foreach ($stores as $store) {
            if (Mage::getStoreConfig($path, $store->getId())) {
                $storeIds[] = $store->getId();
            }
        }

        return $storeIds;
    }

    /**
     * @param     $path
     * @param int $storeId
     *
     * @return mixed
     */
    public function getUncachedConfigValue($path, $storeId = 0)
    {
        /** @var Mage_Core_Model_Resource_Config_Data_Collection $collection */
        $collection = Mage::getModel('core/config_data')->getCollection()->addFieldToFilter('path', $path);
        if ($storeId == 0) {
            $collection = $collection->addFieldToFilter('scope_id', 0)->addFieldToFilter('scope', 'default');
        } else {
            $collection = $collection->addFieldToFilter('scope_id', $storeId)->addFieldToFilter('scope', 'stores');
        }

        return $collection->getFirstItem()->getValue();
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     * @param Mage_Catalog_Model_Product $parent
     * @param                            $parentAttributes
     *
     * @return array|bool
     */
    public function getProductDataRow($product, $config, $parent, $parentAttributes)
    {
        $dataRow = array();

        if (!$this->validateParent($parent, $config, $product)) {
            $parent = '';
        }

        if (!$this->validateProduct($product, $config, $parent)) {
            return false;
        }

        foreach ($config['field'] as $type => $attribute) {
            $value = null;
            $simple = null;
            $productData = $product;
            if ($parent) {
                if (isset($attribute['parent'])) {
                    if ($attribute['parent'] > 0) {
                        $productData = $parent;
                        $simple = $product;
                    }
                }
            }

            if (isset($attribute['parent']) && ($attribute['parent'] == 2) && !$parent) {
                continue;
            }

            if (isset($attribute['source']) || ($type == 'image_link') || ($type == 'condition')) {
                $value = $this->getAttributeValue(
                    $type,
                    $attribute,
                    $config,
                    $productData,
                    $simple,
                    $parentAttributes
                );
            }

            if (!empty($attribute['multi']) && empty($attribute['conditional']) && empty($attribute['condition'])) {
                foreach ($attribute['multi'] as $multi) {
                    $value = $this->getAttributeValue(
                        $type,
                        $multi,
                        $config,
                        $productData,
                        $simple,
                        $parentAttributes
                    );
                    if (!empty($value)) {
                        break;
                    }
                }
            }

            if (!empty($attribute['static'])) {
                $value = $attribute['static'];
            }

            if (!empty($attribute['config'])) {
                $value = $config[$attribute['config']];
            }

            if (!empty($attribute['condition'])) {
                $value = $this->getCondition(
                    $attribute['condition'],
                    $productData,
                    $attribute
                );
            }

            if (!empty($attribute['conditional'])) {
                $value = $this->getConditional($attribute, $productData);
            }

            if (!empty($attribute['custom'])) {
                $value = $this->getCustom($attribute, $productData);
            }

            if (!empty($attribute['collection'])) {
                if ($dataCollection = $this->getAttributeCollection($type, $config, $productData)) {
                    $value = $dataCollection;
                }
            }

            if (!is_array($value) && isset($attribute['action'])) {
                $value = $this->cleanData($value, $attribute['action']);
            }

            $label = $attribute['label'];
            if (is_numeric($label)) {
                $label = 'Field_' . $label;
            }

            if (is_array($value) && isset($value['image'])) {
                $dataRow = array_merge($dataRow, $value);
            } else {
                $dataRow[$label] = $value;
            }
        }

        return $dataRow;
    }

    /**
     * @param Mage_Catalog_Model_Product $parent
     * @param                            $config
     * @param Mage_Catalog_Model_Product $product
     *
     * @return bool
     */
    public function validateParent($parent, $config, $product)
    {
        return $this->validateProduct($product, $config, $parent);
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     * @param Mage_Catalog_Model_Product $parent
     *
     * @return bool
     */
    public function validateProduct($product, $config, $parent)
    {
        if (empty($config['skip_validation'])) {
            if ($product['visibility'] == 1) {
                if (empty($parent)) {
                    return false;
                }

                if ($parent['status'] != 1) {
                    return false;
                }
            }

            if (!empty($config['filter_exclude'])) {
                if ($product[$config['filter_exclude']] == 1) {
                    return false;
                }
            }

            if (!empty($config['stock_exclude'])) {
                if ($config['stock_data'] == 'qty') {
                    if ($product->getTypeId() == 'simple') {
                        if ($product->getQty() < 1) {
                            return false;
                        }
                    }
                }

                if ($product->getUseConfigManageStock()) {
                    $manageStock = $config['inventory']['config_manage_stock'];
                } else {
                    $manageStock = $product->getManageStock();
                }

                if ($manageStock) {
                    if (!$product['is_in_stock']) {
                        return false;
                    }
                }
            }

            if (!empty($config['conf_exclude_parent'])) {
                if ($product->getTypeId() == 'configurable') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param                            $type
     * @param                            $attribute
     * @param                            $config
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Catalog_Model_Product $simple
     * @param                            $parentAttributes
     *
     * @return array|bool
     */
    public function getAttributeValue($type, $attribute, $config, $product, $simple, $parentAttributes)
    {

        if (strpos($type, 'extrafields') !== false) {
            $type = isset($attribute['source']) ? $attribute['source'] : '';
        }

        switch ($type) {
            case 'product_url':
                $value = $this->getProductUrl($product, $config, $simple, $parentAttributes);
                break;
            case 'image_link':
                $value = $this->getProductImage($product, $config);
                break;
            case 'condition':
                $value = $this->getProductCondition($product, $config);
                break;
            case 'availability':
                $value = $this->getProductAvailability($product, $config);
                break;
            case 'weight':
                $value = $this->getProductWeight($product, $config);
                break;
            case 'bundle':
                $value = $this->getProductBundle($product);
                break;
            case 'is_in_stock':
                $value = $this->getIsInStock($product, $config);
                break;
            case 'is_salable':
                $value = $product->getIsSalable();
                break;
            case 'parent_id':
                $value = $this->getProductData($product, $attribute);
                break;
            case 'attribute_set_id':
                $value = $this->getAttributeSetName($product);
                break;
            case 'categories':
                $value = $this->getProductCategories($product, $config);
                break;
            case 'manage_stock':
            case 'min_sale_qty':
            case 'qty_increments':
            case 'allow_backorder':
                $value = $this->getStockValue($type, $product, $config);
                break;
            default:
                if (!empty($attribute['source'])) {
                    $value = $this->getProductData($product, $attribute, $config);
                } else {
                    $value = '';
                }
                break;
        }

        if (!empty($value)) {
            if (!empty($attribute['suffix'])) {
                if (!empty($config[$attribute['suffix']])) {
                    $value .= $config[$attribute['suffix']];
                }
            }
        } else {
            if (!empty($attribute['default'])) {
                $value = $attribute['default'];
            }
        }

        return $value;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     * @param Mage_Catalog_Model_Product $simple
     * @param                            $parentAttributes
     *
     * @return mixed|string
     */
    public function getProductUrl($product, $config, $simple, $parentAttributes)
    {
        $url = '';
        if (!empty($parent)) {
            if ($parent->getRequestPath()) {
                $url = Mage::helper('core')->escapeHtml(trim($config['website_url'] . $parent->getRequestPath()));
            }

            if (empty($url)) {
                if ($parent->getUrlKey()) {
                    $url = Mage::helper('core')->escapeHtml(trim($config['website_url'] . $parent->getUrlKey()));
                }
            }
        } else {
            if ($product->getRequestPath()) {
                $url = Mage::helper('core')->escapeHtml(trim($config['website_url'] . $product->getRequestPath()));
            }

            if (empty($url)) {
                if ($product->getUrlKey()) {
                    $url = Mage::helper('core')->escapeHtml(trim($config['website_url'] . $product->getUrlKey()));
                }
            }
        }

        if (!empty($config['product_url_suffix'])) {
            if (strpos($url, $config['product_url_suffix']) === false) {
                $url = $url . $config['product_url_suffix'];
            }
        }

        if (!empty($config['url_suffix'])) {
            $url = $url . '?' . $config['url_suffix'];
        }

        if (!empty($simple) && !empty($config['conf_switch_urls'])) {
            if ($product->getTypeId() == 'configurable') {
                $productAttributeOptions = $parentAttributes[$product->getEntityId()];
                $urlExtra = '';
                foreach ($productAttributeOptions as $productAttribute) {
                    if ($id = $simple->getResource()->getAttributeRawValue(
                        $simple->getId(),
                        $productAttribute['attribute_code'], $config['store_id']
                    )
                    ) {
                        $urlExtra .= $productAttribute['attribute_id'] . '=' . $id . '&';
                    }
                }

                if (!empty($urlExtra)) {
                    $url = $url . '#' . rtrim($urlExtra, '&');
                }
            }
        }

        return $url;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     *
     * @return array|string
     */
    public function getProductImage($product, $config)
    {
        $imageData = array();
        if (!empty($config['image_resize']) && !empty($config['image_size'])) {
            $imageFile = $product->getData($config['image_source']);
            /** @var Mage_Catalog_Model_Product_Image $imageModel */
            $imageModel = Mage::getModel('catalog/product_image')
                ->setSize($config['image_size'])
                ->setDestinationSubdir($config['image_source'])
                ->setBaseFile($imageFile);
            if (!$imageModel->isCached()) {
                $imageModel->resize()->saveFile();
            }

            $productImage = $imageModel->getUrl();
            return (string)$productImage;
        } else {
            $image = '';
            if (!empty($config['media_attributes'])) {
                foreach ($config['media_attributes'] as $mediaAtt) {
                    if ($mediaAtt == 'base') {
                        $mediaAtt = 'image';
                    }

                    $mediaData = $product->getData($mediaAtt);
                    if (!empty($mediaData)) {
                        if ($mediaData != 'no_selection') {
                            $image = $this->checkImagePath($mediaData, $config['media_image_url']);
                            $imageData['image'][$mediaAtt] = $image;
                        }
                    }
                }
            } else {
                if ($product->getThumbnail()) {
                    if ($product->getThumbnail() != 'no_selection') {
                        $image = $this->checkImagePath($product->getThumbnail(), $config['media_image_url']);
                        $imageData['image']['thumbnail'] = $image;
                    }
                }

                if ($product->getSmallImage()) {
                    if ($product->getSmallImage() != 'no_selection') {
                        $image = $this->checkImagePath($product->getSmallImage(), $config['media_image_url']);
                        $imageData['image']['small_image'] = $image;
                    }
                }

                if ($product->getImage()) {
                    if ($product->getImage() != 'no_selection') {
                        $image = $this->checkImagePath($product->getImage(), $config['media_image_url']);
                        $imageData['image']['image'] = $image;
                    }
                }
            }

            if (!empty($config['images'])) {
                $imageData['g:image_link'] = $image;
                $container = new Varien_Object(array('attribute' => new Varien_Object(array('id' => $config['media_gallery_id']))));
                $imgProduct = new Varien_Object(array('id' => $product->getId(), 'store_id' => $config['store_id']));
                $gallery = Mage::getResourceModel('catalog/product_attribute_backend_media')
                    ->loadGallery($imgProduct, $container);
                $i = 1;
                usort(
                    $gallery, function ($a, $b) {
                    return $a['position_default'] > $b['position_default'];
                    }
                );
                foreach ($gallery as $galImage) {
                    if ($galImage['disabled'] == 0) {
                        $imageData['image']['all']['image_' . $i] = $this->checkImagePath(
                            $galImage['file'],
                            $config['media_image_url']
                        );
                        $imageData['image']['last'] = $this->checkImagePath(
                            $galImage['file'],
                            $config['media_image_url']
                        );
                        if ($i == 1) {
                            $imageData['image']['first'] = $this->checkImagePath(
                                $galImage['file'],
                                $config['media_image_url']
                            );
                        }

                        $i++;
                    }
                }

                return $imageData;
            } else {
                if (!empty($imageData['image']['image'])) {
                    return $imageData['image']['image'];
                } else {
                    return $image;
                }
            }
        }
    }

    /**
     * @param $path
     * @param $mediaUrl
     *
     * @return string
     */
    public function checkImagePath($path, $mediaUrl)
    {
        if (substr($path, 0, 4) === 'http') {
            return $path;
        }

        if ($path[0] != '/') {
            return $mediaUrl . '/' . $path;
        } else {
            return $mediaUrl . $path;
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     *
     * @return bool
     */
    public function getProductCondition($product, $config)
    {
        if (isset($config['condition_attribute'])) {
            if ($condition = $product->getAttributeText($config['condition_attribute'])) {
                return $condition;
            } elseif ($condition = $product->getData($config['condition_attribute'])) {
                return $condition;
            } else {
                return false;
            }
        }

        if (!empty($config['condition_default'])) {
            return $config['condition_default'];
        }

        return false;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     *
     * @return mixed
     */
    public function getProductAvailability($product, $config)
    {
        if (!empty($config['stock_instock'])) {
            if ($product->getUseConfigManageStock()) {
                $manageStock = $config['inventory']['config_manage_stock'];
            } else {
                $manageStock = $product->getManageStock();
            }

            if ($manageStock) {
                if ($product['is_in_stock']) {
                    $availability = $config['stock_instock'];
                } else {
                    $availability = $config['stock_outofstock'];
                }
            } else {
                $availability = $config['stock_instock'];
            }

            return $availability;
        }

        return false;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     *
     * @return string
     */
    public function getProductWeight($product, $config)
    {
        if (!empty($config['weight'])) {
            $weight = (float)$product->getWeight();
            $weight = number_format($weight, 2, '.', '');
            if (isset($config['weight_units'])) {
                $weight = $weight . ' ' . $config['weight_units'];
            }

            if (!empty($weight)) {
                return $weight;
            }
        }

        return '';
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     *
     * @return string
     */
    public function getProductBundle($product)
    {
        if ($product->getTypeId() == 'bundle') {
            return 'true';
        }

        return false;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     *
     * @return string
     */
    public function getIsInStock($product, $config)
    {
        if ($product->getUseConfigManageStock()) {
            $manageStock = $config['inventory']['config_manage_stock'];
        } else {
            $manageStock = $product->getManageStock();
        }

        if ($manageStock) {
            return $product->getIsInStock();
        } else {
            return "1";
        }
    }

    /**
     * @param       Mage_Catalog_Model_Product $product
     * @param                                  $data
     * @param array                            $config
     *
     * @return string
     */
    public function getProductData($product, $data, $config = array())
    {
        $type = !empty($data['type']) ? $data['type'] : '';
        $source = $data['source'];
        $value = '';

        try {
            switch ($type) {
                case 'price':
                    if (!empty($product[$source])) {
                        $value = number_format($product[$source], 2, '.', '');
                        if (!empty($config['currency'])) {
                            $value .= ' ' . $config['currency'];
                        }
                    }
                    break;
                case 'select':
                    if (!empty($source) && !empty($product)) {
                        $value = $product->getAttributeText($source);
                    }
                    break;
                case 'multiselect':
                    if (is_array($product->getAttributeText($source))) {
                        $value = implode(',', $product->getAttributeText($source));
                    } else {
                        $value = $product->getAttributeText($source);
                    }
                    break;
                default:
                    if (isset($product[$source])) {
                        $value = $product[$source];
                    }
                    break;
            }
        } catch (\Exception $e) {
            $this->addToLog('getProductData', $e->getMessage(), 2);
        }

        return $value;
    }

    /**
     * @param      $type
     * @param      $msg
     * @param int  $level
     * @param bool $force
     */
    public function addToLog($type, $msg, $level = 6, $force = false)
    {
        if (is_array($msg)) {
            $msg = json_encode($msg);
        }

        Mage::log($type . ': ' . $msg, $level, self::LOG_FILENAME, $force);
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     *
     * @return mixed
     */
    public function getAttributeSetName($product)
    {
        $attributeSetId = $product->getAttributeSetId();
        /** @var Mage_Eav_Model_Entity_Attribute_Set $attributeSet */
        $attributeSet = Mage::getModel('eav/entity_attribute_set')->load($attributeSetId);

        if ($attributeSet->getId()) {
            return $attributeSet->getAttributeSetName();
        }

        return null;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     *
     * @return array
     */
    public function getProductCategories($product, $config)
    {
        $productsCat = array();
        if (isset($config['category_data'])) {
            $categoryData = $config['category_data'];
            $categoryIds = $product->getCategoryIds();
            if (!empty($config['category_full'])) {
                $path = array();
                foreach ($categoryIds as $categoryId) {
                    if (isset($categoryData[$categoryId])) {
                        $path[] = $categoryData[$categoryId]['name'];
                    }
                }

                $productsCat = array('path' => $path);
            } else {
                foreach ($categoryIds as $categoryId) {
                    if (isset($categoryData[$categoryId])) {
                        $productsCat[] = $categoryData[$categoryId];
                    }
                }
            }

            return $this->getSortedArray($productsCat, 'level');
        }

        return $productsCat;
    }

    /**
     * @param $data
     * @param $sort
     *
     * @return array
     */
    public function getSortedArray($data, $sort)
    {
        $code = "return strnatcmp(\$a['$sort'], \$b['$sort']);";
        usort($data, create_function('$a,$b', $code));

        return array_reverse($data);
    }

    /**
     * @param                            $field
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     *
     * @return bool|string
     */
    public function getStockValue($field, $product, $config)
    {
        $inventory = $config['inventory'];
        if ($field == 'manage_stock') {
            if ($product->getData('use_config_manage_stock')) {
                return $inventory['config_manage_stock'];
            } else {
                return $product->getData('manage_stock');
            }
        }

        if ($field == 'min_sale_qty') {
            if ($product->getData('use_config_min_sale_qty')) {
                return $inventory['config_min_sale_qty'];
            } else {
                return $product->getData('min_sale_qty');
            }
        }

        if ($field == 'allow_backorder') {
            if (!empty($config['stock_exclude'])) {
                return 0;
            }

            if (!$product->getData('is_in_stock')) {
                return 0;
            }

            if ($product->getData('use_config_backorders')) {
                return $inventory['config_backorders'];
            } else {
                if ($product->getData('backorders') > 0) {
                    return 1;
                }

                return 0;
            }
        }

        if ($field == 'qty_increments') {
            if ($product->getData('use_config_enable_qty_inc')) {
                if (!$inventory['config_enable_qty_inc']) {
                    return false;
                }
            } else {
                if (!$product->getData('enable_qty_inc')) {
                    return false;
                }
            }

            if ($product->getData('use_config_qty_increments')) {
                return $inventory['config_qty_increments'];
            } else {
                return $product->getData('qty_increments');
            }
        }

        return '';
    }

    /**
     * @param                                $conditions
     * @param Mage_Catalog_Model_Product     $product
     * @param                                $attribute
     *
     * @return string
     */
    public function getCondition($conditions, $product, $attribute)
    {
        $data = null;
        $value = $product->getData($attribute['source']);

        foreach ($conditions as $condition) {
            $ex = explode(':', $condition);
            if ($ex['0'] == '*') {
                $data = str_replace($ex[0] . ':', '', $condition);
            }

            if ($value == $ex['0']) {
                $data = str_replace($ex[0] . ':', '', $condition);
            }
        }

        if (!empty($attribute['multi'])) {
            $attributes = $attribute['multi'];
            foreach ($attributes as $att) {
                $data = str_replace('{{' . $att['source'] . '}}', $this->getProductData($product, $att), $data);
            }
        }

        return $data;
    }

    /**
     * @param                            $attribute
     * @param Mage_Catalog_Model_Product $product
     *
     * @return mixed|string
     */
    public function getConditional($attribute, $product)
    {
        $dataIf = null;
        $dataElse = null;
        $conditions = $attribute['conditional'];
        $attributes = $attribute['multi'];

        $values = array();
        foreach ($attributes as $att) {
            $values['{{' . $att['source'] . '}}'] = $this->getProductData($product, $att);
        }

        foreach ($conditions as $condition) {
            $ex = explode(':', $condition);
            if ($ex['0'] == '*') {
                if (isset($ex['1'])) {
                    $dataElse = $ex['1'];
                }
            } else {
                if (!empty($values[$ex['0']]) && isset($ex['1'])) {
                    $dataIf = $ex['1'];
                }
            }
        }

        foreach ($values as $key => $value) {
            $dataIf = str_replace($key, $value, $dataIf);
            $dataElse = str_replace($key, $value, $dataElse);
        }

        if (!empty($attribute['action'])) {
            $dataIf = $this->cleanData($dataIf, $attribute);
            $dataElse = $this->cleanData($dataElse, $attribute);
        }

        if (!empty($dataIf)) {
            return $dataIf;
        }

        return $dataElse;
    }

    /**
     * @param        $st
     * @param string $action
     *
     * @return mixed|string
     */
    public function cleanData($st, $action = '')
    {
        if ($action) {
            $breaks = array(
                '<br>',
                '<br/>',
                '<br />',
                '</p>',
                '</p >',
                '</h1>',
                '</h1 >',
                '</h2>',
                '</h2 >',
                '</h3>',
                '</h3 >',
                '</h4>',
                '</h4 >',
                '</h5>',
                '</h5 >',
                '<ul>',
                '<ul >',
                '</ul>',
                '</ul >',
                '</h6>',
                '</h6 >',
                '<hr>',
                '<hr >',
                '</hr>',
                '</hr >',
                '</li>',
                '<li>',
                '</li >'
            );

            $actions = explode('_', $action);
            if (in_array('striptags', $actions)) {
                $st = $this->stripTags($st);
                $st = trim($st);
            }

            if (in_array('replacetags', $actions)) {
                $st = str_replace(array("\r", "\n"), "", $st);
                $st = str_replace("<li>", "- ", $st);
                $st = str_replace($breaks, '\n', $st);
                $st = $this->stripTags($st);
                $st = rtrim($st);
            }

            if (in_array('replacetagsn', $actions)) {
                $st = str_replace(array("\r", "\n"), "", $st);
                $st = str_replace("<li>", "- ", $st);
                $st = str_replace($breaks, '\\' . '\n', $st);
                $st = $this->stripTags($st);
                $st = rtrim($st);
            }

            if (in_array('rn', $actions)) {
                $st = str_replace(array("\r", "\n"), "", $st);
            }

            if (in_array('truncate', $actions)) {
                $st = Mage::helper('core/string')->truncate($st, '5000');
            }

            if (in_array('truncate150', $actions)) {
                $st = Mage::helper('core/string')->truncate($st, '150');
            }

            if (in_array('uppercheck', $actions)) {
                if (strtoupper($st) == $st) {
                    $st = ucfirst(strtolower($st));
                }
            }

            if (in_array('cdata', $actions)) {
                $st = '<![CDATA[' . $st . ']]>';
            }

            if (in_array('round', $actions)) {
                if (!empty($actions[1])) {
                    if ($st > $actions[1]) {
                        $st = $actions[1];
                    }
                }

                $st = round($st);
            }

            if (in_array('boolean', $actions)) {
                ($st > 0 ? $st = 1 : $st = 0);
            }
        }

        return $this->stripInvalidXml($st);
    }

    /**
     * @param $string
     *
     * @return string
     */
    public function stripInvalidXml($string)
    {
        $regex = '/(
            [\xC0-\xC1] # Invalid UTF-8 Bytes
            | [\xF5-\xFF] # Invalid UTF-8 Bytes
            | \xE0[\x80-\x9F] # Overlong encoding of prior code point
            | \xF0[\x80-\x8F] # Overlong encoding of prior code point
            | [\xC2-\xDF](?![\x80-\xBF]) # Invalid UTF-8 Sequence Start
            | [\xE0-\xEF](?![\x80-\xBF]{2}) # Invalid UTF-8 Sequence Start
            | [\xF0-\xF4](?![\x80-\xBF]{3}) # Invalid UTF-8 Sequence Start
            | (?<=[\x0-\x7F\xF5-\xFF])[\x80-\xBF] # Invalid UTF-8 Sequence Middle
            | (?<![\xC2-\xDF]|[\xE0-\xEF]|[\xE0-\xEF][\x80-\xBF]|[\xF0-\xF4]|[\xF0-\xF4][\x80-\xBF]|[\xF0-\xF4][\x80-\xBF]{2})[\x80-\xBF] # Overlong Sequence
            | (?<=[\xE0-\xEF])[\x80-\xBF](?![\x80-\xBF]) # Short 3 byte sequence
            | (?<=[\xF0-\xF4])[\x80-\xBF](?![\x80-\xBF]{2}) # Short 4 byte sequence
            | (?<=[\xF0-\xF4][\x80-\xBF])[\x80-\xBF](?![\x80-\xBF]) # Short 4 byte sequence (2)
        )/x';

        return preg_replace($regex, '', $string);
    }

    /**
     * @param                            $attribute
     * @param Mage_Catalog_Model_Product $product
     *
     * @return string
     */
    public function getCustom($attribute, $product)
    {
        $data = $attribute['custom'];
        preg_match_all("/{{([^}]*)}}/", $data, $attributes);
        if (!empty($attributes)) {
            foreach ($attributes[0] as $key => $value) {
                if (!empty($product[$attributes[1][$key]])) {
                    if ($attText = $product->getAttributeText($attributes[1][$key])) {
                        if (is_array($attText)) {
                            $attText = implode(',', $attText);
                        }

                        $data = str_replace($value, $attText, $data);
                    } else {
                        $data = str_replace($value, $product[$attributes[1][$key]], $data);
                    }
                } else {
                    $data = str_replace($value, '', $data);
                }
            }
        }

        return trim($data);
    }

    /**
     * @param                                $type
     * @param                                $config
     * @param Mage_Catalog_Model_Product     $product
     *
     * @return array
     */
    public function getAttributeCollection($type, $config, $product)
    {
        if ($type == 'price') {
            return $this->getProductPrice($product, $config);
        }

        return array();
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param                            $config
     *
     * @return array
     */
    public function getProductPrice($product, $config)
    {
        $priceData = array();

        switch ($product->getTypeId()) {
            case 'grouped':
                $groupedPrices = $this->getGroupedPrices($product, $config);

                $price = $groupedPrices['min_price'];
                $finalPrice = $groupedPrices['min_price'];
                $minPrice = $groupedPrices['min_price'];
                $maxPrice = $groupedPrices['max_price'];
                $totalPrice = $groupedPrices['total_price'];

                $groupedPriceType = !empty($config['price_grouped']) ? $config['price_grouped'] : 'min';
                if ($groupedPriceType == 'max') {
                    $price = $maxPrice;
                    $finalPrice = $maxPrice;
                }

                if ($groupedPriceType == 'total') {
                    $price = $totalPrice;
                    $finalPrice = $totalPrice;
                }
                break;
            case 'bundle':
                if ($product->getPriceType() == '1') {
                    $price = $this->processPrice($product, $product->getPrice(), $config);
                    $finalPrice = $this->processPrice($product, $product->getFinalPrice(), $config);;
                    $minPrice = $this->processPrice($product, $product->getData('min_price'), $config);
                    $maxPrice = $this->processPrice($product, $product->getData('max_price'), $config);

                    if ((floatval($price) == 0) && (floatval($minPrice) !== 0)) {
                        $price = $minPrice;
                    }
                } else {
                    $bundlePrices = $this->getBundlePrices($product, $config);
                    $price = $this->processPrice($product, $bundlePrices['min_price'], $config);
                    $minPrice = $this->processPrice($product, $bundlePrices['min_price'], $config);
                    $maxPrice = $this->processPrice($product, $bundlePrices['max_price'], $config);

                    if (isset($bundlePrices['configured_price'])) {
                        $configuredPrice = $this->processPrice($product, $bundlePrices['configured_price'], $config);
                    }
                }

                if ($product->getSpecialPrice() > 0) {
                    $today = time();
                    $specialPriceFromDate = $product->getSpecialFromDate();
                    $specialPriceToDate = $product->getSpecialToDate();
                    if ($today >= strtotime($specialPriceFromDate)) {
                        if ($today <= strtotime($specialPriceToDate) || empty($specialPriceToDate)) {
                            $finalPrice = $price * ($product->getSpecialPrice() / 100);
                            $minPrice = $minPrice * ($product->getSpecialPrice() / 100);
                            $maxPrice = $maxPrice * ($product->getSpecialPrice() / 100);
                        }
                    }
                }
                break;
            default:
                $price = $this->processPrice($product, $product->getPrice(), $config);
                $finalPrice = $this->processPrice($product, $product->getFinalPrice(), $config);;
                $minPrice = $this->processPrice($product, $product->getData('min_price'), $config);
                $maxPrice = $this->processPrice($product, $product->getData('max_price'), $config);

                if ((floatval($price) == 0) && (floatval($minPrice) !== 0)) {
                    $price = $minPrice;
                }
                break;
        }

        $priceData['final_price_clean'] = $price;
        $priceData['price'] = $this->formatPrice($price, $config);
        $priceData['regular_price'] = $this->formatPrice($price, $config);

        if (isset($minPrice)) {
            $priceData['min_price'] = $this->formatPrice($minPrice, $config);
        }

        if (isset($maxPrice)) {
            $priceData['max_price'] = $this->formatPrice($maxPrice, $config);
        }

        if (isset($configuredPrice)) {
            $priceData['configured_price'] = $this->formatPrice($configuredPrice, $config);
        }

        if (isset($finalPrice) && ($finalPrice > 0) && ($finalPrice < $price)) {
            $today = time();
            $specialPriceFromDate = $product->getSpecialFromDate();
            $specialPriceToDate = $product->getSpecialToDate();
            if ($today >= strtotime($specialPriceFromDate)) {
                if ($today <= strtotime($specialPriceToDate) || empty($specialPriceToDate)) {
                    $priceData['sales_date_start'] = $specialPriceFromDate;
                    $priceData['sales_date_end'] = $specialPriceToDate;
                }
            }

            $priceData['sales_price'] = $this->formatPrice($finalPrice, $config);
        }

        return $priceData;
    }

    /**
     * @param $config
     *
     * @return int
     */
    public function getPriceMarkup($config)
    {
        $markup = 1;
        if (!empty($config['price_add_tax']) && !empty($config['price_add_tax_perc'])) {
            $markup = 1 + ($config['price_add_tax_perc'] / 100);
        }

        if ($config['base_currency_code'] != $config['currency']) {
            /** @var Mage_Directory_Helper_Data $directory */
            $directory = Mage::helper('directory');
            $exchangeRate = $directory->currencyConvert(1, $config['base_currency_code'], $config['currency']);
            $markup = ($markup * $exchangeRate);
        }

        return $markup;
    }

    /**
     * @param        Mage_Catalog_Model_Product $product
     * @param string                            $config
     *
     * @return bool|mixed|number
     */
    public function getGroupedPrices($product, $config)
    {
        $prices = array();
        $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
        foreach ($associatedProducts as $item) {
            $priceAssociated = $this->processPrice($item, $item->getFinalPrice(), $config);
            if ($priceAssociated > 0) {
                $prices[] = $priceAssociated;
            }
        }

        return array(
            'min_price'   => min($prices),
            'max_price'   => max($prices),
            'total_price' => array_sum($prices)
        );
    }

    /**
     * @param Mage_Catalog_Model_Product     $product
     * @param                                $price
     * @param                                $config
     *
     * @return float|string
     */
    public function processPrice($product, $price, $config)
    {
        if (!empty($config['markup'])) {
            $price = $price * $config['markup'];
        }

        if (isset($config['use_tax'])) {
            $price = Mage::helper('tax')->getPrice($product, $price, $config['use_tax']);
        }

        return $price;
    }

    /**
     * @param        Mage_Catalog_Model_Product $product
     * @param array                             $config
     *
     * @return bool|mixed|number
     */
    public function getBundlePrices($product, $config)
    {
        $minimalPrice = null;
        $maximalPrice = null;
        $configuredPrice = null;

        /** @var Mage_Bundle_Model_Product_Type $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $typeInstance->setStoreFilter($config['store_id'], $product);

        $optionCollection = $typeInstance->getOptionsCollection($product);
        $selectionCollection = $typeInstance->getSelectionsCollection($typeInstance->getOptionsIds($product), $product);

        $options = $optionCollection->appendSelections(
            $selectionCollection, false,
            Mage::helper('catalog/product')->getSkipSaleableCheck()
        );

        foreach ($options as $option) {
            $prices = array();
            $confFlag = false;
            $selections = $option->getSelections();
            foreach ($selections as $key => $selection) {
                if ($selection->getSelectionQty() > 0) {
                    $selectionPrice = $selection->getFinalPrice() * $selection->getSelectionQty();
                } else {
                    $selectionPrice = $selection->getFinalPrice();
                }

                $prices[] = $this->processPrice($selection, $selectionPrice, $config);

                if ($selection->getIsDefault()) {
                    $configuredPrice += $this->processPrice($selection, $selectionPrice, $config);
                    $confFlag = true;
                }
            }

            if ($option->getRequired()) {
                $minimalPrice += min($prices);
                if (!$confFlag) {
                    $configuredPrice += min($prices);
                }
            }

            if ($option->getType() == 'checkbox' || $option->getType() == 'multi') {
                foreach ($prices as $price) {
                    $maximalPrice += $price;
                }
            } else {
                $maximalPrice += max($prices);
            }
        }

        return array(
            'min_price'        => $minimalPrice,
            'max_price'        => $maximalPrice,
            'configured_price' => $configuredPrice
        );
    }

    /**
     * @param $price
     * @param $config
     *
     * @return string
     */
    public function formatPrice($price, $config)
    {
        $price = number_format(floatval(str_replace(',', '.', $price)), 2, '.', '');
        if (!empty($config['use_currency']) && ($price >= 0)) {
            $price .= ' ' . $config['currency'];
        }

        return $price;
    }


    /**
     * @param Mage_Catalog_Model_Resource_Product_Collection $parents
     * @param                                                $config
     *
     * @return array
     */
    public function getConfigurableAttributesAsArray($parents, $config)
    {
        $configurableAttributes = array();

        if (empty($parents)) {
            return $configurableAttributes;
        }

        if (empty($config['conf_switch_urls'])) {
            return $configurableAttributes;
        }

        foreach ($parents as $parent) {
            if ($parent->getTypeId() == 'configurable') {
                $configurableAttributes[$parent->getEntityId()] = $parent->getTypeInstance(true)
                    ->getConfigurableAttributesAsArray($parent);
            }
        }

        return $configurableAttributes;
    }

    /**
     * @param $config
     *
     * @return string
     */
    public function getTaxUsage($config)
    {
        if (!empty($config['force_tax'])) {
            if ($config['force_tax'] == 'incl') {
                return 'true';
            } else {
                return '';
            }
        } else {
            return 'true';
        }
    }

    /**
     * @param        $attributes
     * @param array  $config
     *
     * @return mixed
     */
    public function addAttributeData($attributes, $config = array())
    {
        /** @var Mage_Eav_Model_Entity_Attribute $attributeModel */
        $attributeModel = Mage::getModel('eav/entity_attribute');

        if (!empty($config['conf_fields'])) {
            $confAttributes = explode(',', $config['conf_fields']);
        } else {
            $confAttributes = array();
        }

        foreach ($attributes as $key => $value) {
            if (!empty($value['source']) || !empty($value['multi'])) {
                try {
                    if (!empty($value['multi'])) {
                        $multipleSources = explode(',', $value['multi']);
                        $sourcesArray = array();
                        foreach ($multipleSources as $source) {
                            if (strlen($source)) {
                                $attribute = $attributeModel->loadByCode('catalog_product', $source);
                                if ($attribute->getAttributeCode() == $source) {
                                    $type = $attribute->getFrontendInput();
                                } else {
                                    $type = '';
                                }

                                $sourcesArray[] = array('type' => $type, 'source' => $source);
                            }
                        }

                        if (!empty($sourcesArray)) {
                            $attributes[$key]['multi'] = $sourcesArray;
                            if (in_array(
                                $attributes[$key]['source'],
                                array('mm-actions-custom', 'mm-actions-conditional', 'mm-actions-multi')
                            )) {
                                unset($attributes[$key]['source']);
                                unset($value['source']);
                            }
                        } else {
                            unset($attributes[$key]);
                        }
                    }

                    if (!empty($value['source'])) {
                        $attribute = $attributeModel->loadByCode('catalog_product', $value['source']);
                        if ($attribute->getAttributeCode() == $value['source']) {
                            $type = $attribute->getFrontendInput();
                            $attributes[$key]['type'] = $type;
                        }
                    }
                } catch (\Exception $e) {
                    unset($attributes[$key]);
                    $this->addToLog('addAttributeData', $e->getMessage(), 2);
                }
            }

            if (in_array($key, $confAttributes)) {
                $attributes[$key]['parent'] = 1;
            }
        }

        return $attributes;
    }

    /**
     * @param $config
     * @param $storeId
     *
     * @return array
     */
    public function getCategoryData($config, $storeId)
    {
        $defaultAttributes = array('entity_id', 'path', 'name', 'level');
        $attributes = $defaultAttributes;

        if (!empty($config['category_custom'])) {
            $attributes[] = $config['category_custom'];
        }

        if (!empty($config['category_replace'])) {
            $attributes[] = $config['category_replace'];
        }

        if (!empty($config['category_exclude'])) {
            $attributes[] = $config['category_exclude'];
        }

        // CHECK IF NEW ATTRIBUTES ARE AVAILABLE
        try {
            Mage::getModel('catalog/category')
                ->setStoreId($storeId)
                ->getCollection()
                ->addAttributeToSelect($attributes)
                ->setCurPage(1)
                ->setPageSize(1)
                ->getFirstItem();
        } catch (Exception $e) {
            $this->addToLog('getCategoryData', $e->getMessage(), 2);
        }

        if (empty($e)) {
            /** @var Mage_Catalog_Model_Resource_Category_Collection $categories */
            $categories = Mage::getModel('catalog/category')
                ->setStoreId($storeId)
                ->getCollection()
                ->addAttributeToSelect($attributes)
                ->addFieldToFilter('path', array('like' => '%/' . $config['root_category_id'] . '/%'))
                ->addFieldToFilter('is_active', array('eq' => 1));
        } else {
            /** @var Mage_Catalog_Model_Resource_Category_Collection $categories */
            $categories = Mage::getModel('catalog/category')
                ->setStoreId($storeId)
                ->getCollection()
                ->addAttributeToSelect($defaultAttributes)
                ->addFieldToFilter('path', array('like' => '%/' . $config['root_category_id'] . '/%'))
                ->addFieldToFilter('is_active', array('eq' => 1));
        }

        if (!empty($config['filter_enabled'])) {
            $type = $config['filter_type'];
            $fCategories = explode(',', $config['filter_cat']);
            if ($type && $fCategories) {
                if ($type == 'include') {
                    $categories->addAttributeToFilter('entity_id', array('in' => $fCategories));
                } else {
                    $categories->addAttributeToFilter('entity_id', array('nin' => $fCategories));
                }
            }
        }

        $_categories = array();

        foreach ($categories as $cat) {
            $custom = '';
            $name = '';
            $exclude = 0;
            if (!empty($config['category_custom'])) {
                if (!empty($cat[$config['category_custom']])) {
                    $custom = $cat[$config['category_custom']];
                }
            }

            if (!empty($config['category_replace'])) {
                if (!empty($cat[$config['category_replace']])) {
                    $custom = $cat[$config['category_replace']];
                }
            }

            if (isset($config['category_exclude'])) {
                if (!empty($cat[$config['category_exclude']])) {
                    $exclude = $cat[$config['category_exclude']];
                }
            }

            if (empty($name)) {
                $name = $cat['name'];
            }

            if ($exclude != 1) {
                $_categories[$cat->getId()] = array(
                    'path'    => $cat['path'],
                    'custom'  => $custom,
                    'name'    => $name,
                    'level'   => $cat['level'],
                    'exclude' => $exclude
                );
            }
        }

        foreach ($_categories as $key => $cat) {
            $path = array();
            $paths = explode('/', $cat['path']);
            foreach ($paths as $p) {
                if (!empty($_categories[$p]['name'])) {
                    if ($_categories[$p]['level'] > 1) {
                        if ($_categories[$p]['custom']) {
                            $path[] = $this->cleanData($_categories[$p]['custom'], 'striptags');
                        } else {
                            $path[] = $this->cleanData($_categories[$p]['name'], 'striptags');
                        }
                    }
                }
            }

            $_categories[$key] = array(
                'path'   => $this->cleanData($path),
                'custom' => $this->cleanData($cat['custom'], 'striptags'),
                'name'   => $this->cleanData($cat['name'], 'striptags'),
                'level'  => $cat['level']
            );
        }

        return $_categories;
    }

    /**
     * @param Mage_Catalog_Model_Resource_Product_Collection $products
     * @param                                                $config
     *
     * @return array
     */
    public function getParentsFromCollection($products, $config)
    {
        $ids = array();
        if (empty($config['conf_enabled'])) {
            return $ids;
        }

        foreach ($products as $product) {
            if ($parentId = $this->getParentData($product)) {
                $ids[$product->getEntityId()] = $parentId;
            }
        }

        return $ids;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    public function getParentData($product)
    {
        if (($product['type_id'] == 'simple')) {
            $configIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
            if ($configIds) {
                return $configIds;
            }

            $groupIds = Mage::getResourceSingleton('catalog/product_link')->getParentIdsByChild(
                $product->getId(),
                Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED
            );

            if ($groupIds) {
                return $groupIds;
            }
        }
    }

    /**
     * @param                                                $config
     * @param Mage_Catalog_Model_Resource_Product_Collection $products
     *
     * @return array
     */
    public function getTypePrices($config, $products)
    {
        $typePrices = array();
        $confEnabled = $config['conf_enabled'];
        $simplePrice = $config['simple_price'];

        if (empty($products)) {
            return $typePrices;
        }

        if (!empty($confEnabled) && empty($simplePrice)) {
            foreach ($products as $product) {
                if ($product->getTypeId() == 'configurable') {
                    $parentId = $product->getEntityId();
                    $attributes = $product->getTypeInstance(true)->getConfigurableAttributes($product);
                    $basePrice = $product->getFinalPrice();
                    $basePriceReg = $product->getPrice();
                    $optionPrices = array();

                    foreach ($attributes as $attribute) {
                        $prices = $attribute->getPrices();
                        foreach ($prices as $value) {
                            $product->setConfigurablePrice(
                                $this->preparePrice(
                                    $value['pricing_value'],
                                    $value['is_percent'], $product
                                )
                            );
                            $product->setParentId(true);
                            Mage::dispatchEvent(
                                'catalog_product_type_configurable_price', array('product' => $product)
                            );
                            $configurablePrice = $product->getConfigurablePrice();
                            $optionPrices[$value['value_index']] = $configurablePrice;
                            $optionPrices[$value['value_index'] . '_reg'] =
                                $this->prepareOldPrice($value['pricing_value'], $value['is_percent'], $product);
                        }
                    }

                    $simple = $product->getTypeInstance()->getUsedProducts();
                    foreach ($simple as $sProduct) {
                        $totalPrice = $basePrice;
                        $totalPriceReg = $basePriceReg;
                        foreach ($attributes as $attribute) {
                            if ($attribute->getProductAttribute() !== null) {
                                $value = $sProduct->getData($attribute->getProductAttribute()->getAttributeCode());
                                if (isset($optionPrices[$value])) {
                                    $totalPrice += $optionPrices[$value];
                                    $totalPriceReg += $optionPrices[$value . '_reg'];
                                }
                            }
                        }

                        $typePrices[$parentId . '_' . $sProduct->getEntityId()] =
                            number_format(($totalPrice * $config['markup']), 2, '.', '');

                        $typePrices[$parentId . '_' . $sProduct->getEntityId() . '_reg'] =
                            number_format(($totalPriceReg * $config['markup']), 2, '.', '');
                    }
                }
            }
        }

        return $typePrices;
    }

    /**
     * @param                            $price
     * @param bool                       $isPercent
     * @param Mage_Catalog_Model_Product $product
     *
     * @return float|int
     */
    public function preparePrice($price, $isPercent = false, $product)
    {
        if ($isPercent && !empty($price)) {
            $price = $product->getFinalPrice() * $price / 100;
        }

        return $price;
    }

    /**
     * @param                            $price
     * @param bool                       $isPercent
     * @param Mage_Catalog_Model_Product $product
     *
     * @return float|int
     */
    public function prepareOldPrice($price, $isPercent = false, $product)
    {
        if ($isPercent && !empty($price)) {
            $price = $product->getPrice() * $price / 100;
        }

        return $price;
    }

    /**
     * @param $dir
     *
     * @return bool
     */
    public function checkOldVersion($dir)
    {
        if ($dir) {
            $dir = Mage::getBaseDir('app') . DS . 'code' . DS . 'local' . DS . 'Magmodules' . DS . $dir;
            return file_exists($dir);
        }

        return false;
    }

    /**
     * @param $attributes
     *
     * @return array
     */
    public function checkFlatCatalog($attributes)
    {
        $nonFlatAttributes = array();
        $skipCheck = array('entity_id', 'sku', 'category_ids', 'image');
        foreach ($attributes as $key => $attribute) {
            if (!empty($attribute['source'])) {
                if (!in_array($attribute['source'], $skipCheck)) {
                    $_attribute = Mage::getModel('eav/entity_attribute')
                        ->loadByCode('catalog_product', $attribute['source']);
                    if ($_attribute->getUsedInProductListing() == 0) {
                        if ($_attribute->getId()) {
                            $nonFlatAttributes[$_attribute->getId()] = $_attribute->getFrontendLabel();
                        }
                    }
                }
            }
        }

        return $nonFlatAttributes;
    }

    /**
     * @param $storeId
     *
     * @return mixed|string
     */
    public function getProductUrlSuffix($storeId)
    {
        $suffix = Mage::getStoreConfig('catalog/seo/product_url_suffix', $storeId);
        if (!empty($suffix)) {
            if ((strpos($suffix, '.') === false) && ($suffix != '/')) {
                $suffix = '.' . $suffix;
            }
        }

        return $suffix;
    }

    /**
     * @return array
     */
    public function getMediaAttributes()
    {
        $mediaTypes = array();
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addFieldToFilter('frontend_input', 'media_image');
        foreach ($attributes as $attribute) {
            $mediaTypes[] = $attribute->getData('attribute_code');
        }

        return $mediaTypes;
    }

    /**
     * @return int
     */
    public function getStoreIdConfig()
    {
        $storeId = 0;
        $code = Mage::getSingleton('adminhtml/config_data')->getStore();
        if (!empty($code)) {
            $storeId = Mage::getModel('core/store')->load($code)->getId();
        }

        return $storeId;
    }

    /**
     * @param $page
     * @param $pages
     * @param $processed
     */
    public function addLog($page, $pages, $processed)
    {
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage < 1024) {
            $usage = $memoryUsage . ' b';
        } elseif ($memoryUsage < 1048576) {
            $usage = round($memoryUsage / 1024, 2) . ' KB';
        } else {
            $usage = round($memoryUsage / 1048576, 2) . ' MB';
        }

        $msg = sprintf(
            'Page: %s/%s | Memory Usage: %s | Products: %s',
            $page,
            $pages,
            $usage,
            $processed
        );

        $this->addToLog('Generation', $msg, '', true);
    }

    /**
     * @param $timeStart
     *
     * @return float|string
     */
    public function getTimeUsage($timeStart)
    {

        $time = round((microtime(true) - $timeStart));
        if ($time > 120) {
            $time = round($time / 60, 1) . ' ' . $this->__('minutes');
        } else {
            $time = round($time) . ' ' . $this->__('seconds');
        }

        return $time;
    }

}
