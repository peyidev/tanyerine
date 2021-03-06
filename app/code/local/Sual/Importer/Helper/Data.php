<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 8/19/18
 * Time: 4:48 PM
 */
ini_set("memory_limit", -1);
//class Mage_Sual_Categoryimporter_Helper_Data extends  Mage_Core_Helper_Abstract{
//
//}

class Sual_Importer_Helper_Data extends Mage_Core_Helper_Abstract
{

    public $parentCategoryId = "";
    public $insertados = 0;
    public $actualizados = 0;
    public $promos = 0;
    public $output = "";

    public $promotionsParentCategory = array(
        'name' => 'Promociones descuento directo',
        'url' => 'promociones-descuento-directo '
    );

    public $promotionsArray = array(
        array(
            'name' => '10 porciento',
            'url' => '10-porciento',
            'id' => 0,
            'value' => 10
        ),
        array(
            'name' => '20 porciento',
            'url' => '20-porciento',
            'id' => 0,
            'value' => 20
        ),
        array(
            'name' => '30 porciento',
            'url' => '30-porciento',
            'id' => 0,
            'value' => 30
        ),
        array(
            'name' => '40 porciento',
            'url' => '40-porciento',
            'id' => 0,
            'value' => 40
        ),
        array(
            'name' => '50 porciento',
            'url' => '50-porciento',
            'id' => 0,
            'value' => 50
        )
    );



    public function execute($executionId, $source)
    {
        $this->output .= "Ejecutado desde <strong>{$source}</strong>\n";
        $this->parentCategoryId = Mage::getModel('catalog/category')
            ->getCollection()
            ->setStoreId(1)
            ->addAttributeToFilter('name', 'SUAL')->getFirstItem()->getId();

        $executionStartTime = microtime(true);
//        $this->importCategories();
//        $executionEndTime1 = microtime(true);
//        $minutes = ($executionEndTime1 - $executionStartTime) / 60;
//        $this->output .=  "<strong>importCategories</strong> tardó <span style='color:#F77812;'>$minutes</span> minutos en ejecutar.\n";

        $this->setupPromoCategories();

        $this->importProducts();
        $executionEndTime2 = microtime(true);
        $minutes = ($executionEndTime2 - $executionStartTime) / 60;
        $this->output .=  "<strong>importProducts</strong> tardó <span style='color:#F77812;'>$minutes</span> minutos en ejecutar.\n";

        $this->importServices();
        $executionEndTime2 = microtime(true);
        $minutes = ($executionEndTime2 - $executionStartTime) / 60;
        $this->output .= "<strong>importServices</strong> tardó <span style='color:#F77812;'>$minutes</span> minutos en ejecutar.\n";
        $this->output .= "<strong>setupPromoCategories</strong> importó <span style='color:#F77812;'>{$this->promos}</span> promociones.\n";

//        $executionEndTime2 = microtime(true);
//        $minutes = ($executionEndTime2 - $executionStartTime) / 60;
//        $this->output .= "<strong>importServices</strong> tardó <span style='color:#F77812;'>$minutes</span> minutos en ejecutar.\n";

        $this->closeExecution($executionId);

    }

    public function closeExecution($executionId)
    {

        $model = Mage::getModel('sual_importer/execute');
        $model->load($executionId);

        $current_date = date('Y-m-d H:i:s');

        $model->addData(
            array(
                "fin" => $current_date,
                "resumen" => $this->output
            )
        );

        //echo strip_tags($this->output);
        $model->save();

    }

    public function imageExists($url)
    {
        return true;
        $file_headers = @get_headers($url);
        return $file_headers[count($file_headers) - 1] == 'Content-Type: image/jpeg';
    }

    public function getImage($url, $product)
    {

        $base_path = Mage::getBaseDir('base');
        $name = $product['seo_url'];
        $sku = $product['sku'];
        $brand = $product['brand'];

        $urlPieces = explode(".", $url);
        $extension = end($urlPieces);
        $img = $sku . '-' . strtoupper($this->getUrl($brand)) . '-' . $name . "." . $extension;
        $whereToSave = $base_path . '/media/imports/' . $img;

        //if (!file_exists($whereToSave))
        file_put_contents($whereToSave, file_get_contents($url));

        return $whereToSave;
    }


    public function getUrl($name)
    {

        $find = array("Á", "É", "Í", "Ó", "Ú", "Ñ", "&", "'", "´", ".", "?");
        $replace = array("A", "E", "I", "O", "Ú", "N", "Y", "", "", "", "");
        return str_replace(' ', '-', strtolower(str_replace($find, $replace, ($name))));
    }


    public function productExists($sku)
    {
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);

        if (!empty($product)) {
            return $product;
        } else {
            return false;
        }
    }

    public function categoryExist($childCategoryName, $parentCategoryId, $inactive = false)
    {

        $parentCategory = Mage::getModel('catalog/category')->load($parentCategoryId);

        $childCategory = Mage::getModel('catalog/category')->getCollection()
            ->addIdFilter(
                !$inactive ? $parentCategory->getChildren() : $parentCategory->getResource()->getChildrenIds($parentCategory)
            )
            ->addAttributeToFilter('name_sap', $childCategoryName)
            ->getFirstItem()->getId();

        if (!empty($childCategory)) {
            return $childCategory;
        } else {
            return false;
        }
    }


    public function importCategories()
    {

        $parentCategoryId = $this->parentCategoryId;
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $newCategories = $connection->query('SELECT category FROM sb_product WHERE category IS NOT NULL GROUP BY category');


        foreach ($newCategories as $category) {

            $category = $this->utf8_converter($category);

            $name = $category['category'];
            $url = $this->getUrl($name);

            $categoryId = $this->categoryExist($name, $parentCategoryId, true);

            if (!$categoryId) {
                //$this->output .=  "Insertando Categoria {$category['category']} URL {$url}\n";
                //echo  "Insertando Categoria {$category['category']} URL {$url}\n";
                $categoryId = $this->insertCategory($name, $url, $parentCategoryId);
            }

            //echo  $categoryId . " CATEGORIA PADRE - \n";


            $subcategories = $connection->query(" SELECT subcategory
                                                    FROM sb_product
                                                  WHERE category = '{$category['category']}' AND subcategory IS NOT NULL AND subcategory <> ''
                                                    GROUP BY subcategory");

            foreach ($subcategories as $subcategory) {

                $subcategory = $this->utf8_converter($subcategory);
                $nameSub = $subcategory['subcategory'];
                $urlSub = $this->getUrl($nameSub);

                $subcategoryId = $this->categoryExist($nameSub, $categoryId, true);

                if (!$subcategoryId) {
                    //echo "\tInsertando subcategoria {$subcategory['subcategory']} URL -> {$urlSub}\n";
                    $subcategoryId = $this->insertCategory($nameSub, $urlSub, $categoryId);
                }

                $lines = $connection->query(" SELECT line
                                                    FROM sb_product
                                                  WHERE category = '{$category['category']}' AND subcategory = '{$subcategory['subcategory']}'  AND line IS NOT NULL AND line <> ''
                                                    GROUP BY line");

                foreach ($lines as $line) {

                    $line = $this->utf8_converter($line);
                    continue;
                    //Remove this to insert lines
                    $nameLine = $line['line'];
                    $urlLine = $this->getUrl($nameLine);

                    $lineId = $this->categoryExist($nameLine, $subcategoryId, true);

                    if (!$lineId) {
                        //$this->output .=  "\t\tInsertando linea {$lineId['line']} URL -> {$urlLine}\n";
                        //echo  "\t\tInsertando linea {$lineId['line']} URL -> {$urlLine}\n";
                        $this->insertCategory($nameLine, $urlLine, $subcategoryId, false);

                    }

                }

            }

        }

    }

    public function importProducts()
    {
        //$this->output .=  "Iniciando";

        $limit = 50000;
        $limitSql = " LIMIT {$limit}";

        $where = ' WHERE last_updated >= "2018-11-29 10:10:00" AND type = "PRODUCTO" OR type = "OBSEQUIO"' . $limitSql;
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $howmanyProducts = $connection->query('SELECT count(*) as howmany FROM sb_product' . $where);
        $totalProducts = 0;

        foreach ($howmanyProducts as $many) {
            $totalProducts = $many;
        }

        $products = $connection->query('SELECT *  FROM sb_product' . $where);

        foreach ($products as $product) {
            $product = $this->utf8_converter($product);
            $this->insertProduct($product);

            // if(!empty($product))
            //     //echo "Productos insertados {$this->insertados} / actualizados {$this->actualizados}.\n";

        }

        if ($limit > 0)
            $totalProducts = $limit;

        $this->output .= "Se procesaron {$totalProducts['howmany']} productos.\n";
        $this->output .= "Productos insertados {$this->insertados} / actualizados {$this->actualizados}.\n";
    }


    public function importServices()
    {
        //$this->output .=  "Iniciando";

        $limit = 50000;
        $limitSql = " LIMIT {$limit}";

        $where = ' WHERE last_updated >= "2018-11-29 10:10:00" AND  type = "SERVICIOS"' . $limitSql;
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $howmanyProducts = $connection->query('SELECT count(*) as howmany FROM sb_product' . $where);
        $totalProducts = 0;

        foreach ($howmanyProducts as $many) {
            $totalProducts = $many;
        }

        $products = $connection->query('SELECT *  FROM sb_product' . $where);

        foreach ($products as $product) {
            $product = $this->utf8_converter($product);
            $this->insertProduct($product, 10);

        }

        if ($limit > 0)
            $totalProducts = $limit;

        $this->output .= "Se procesaron {$totalProducts['howmany']} productos.\n";
        $this->output .= "Productos insertados {$this->insertados} / actualizados {$this->actualizados}.\n";
    }


    public function insertProductBaseAttributes(&$product, $productSual, $urlImage, $attributeSet = 9)
    {

        $idAttribute = $this->addAttributeValue('brand', $productSual['brand']);

        $product
            ->setWebsiteIds(array(1))//website ID the product is assigned to, as an array
            ->setAttributeSetId($attributeSet)//ID of a attribute set named 'default'
            ->setTypeId('simple')//product type
            ->setCreatedAt(strtotime('now'))//product creation time
            ->setSku($productSual['sku'])//SKU
            ->setName($productSual['name'])//product name
            ->setWeight(1)
            ->setStatus(1)//product status (1 - enabled, 2 - disabled)
            ->setTaxClassId(0)//tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)//catalog and search visibility

            ->setPrice($productSual['price'])
            ->setMetaTitle($productSual['seo_meta_title'])
            ->setMetaKeyword($productSual['seo_meta_keywords'])
            ->setMetaDescription($productSual['seo_meta_description'])
            ->setDescription($productSual['description'])
            ->setShortDescription($productSual['description'])
            ->setUrlKey($productSual['seo_url'])
            ->setMediaGallery(array('images' => array(), 'values' => array()))//media gallery initialization
            ->addImageToMediaGallery($this->getImage($urlImage, $productSual), array('image', 'thumbnail', 'small_image'), false, false)//assigning image, thumb and small image to media gallery
            /* Sual eCom */
            ->setBrand($idAttribute)
            ->setAtribute($productSual['attribute'])
            ->setContainerType($productSual['container_type'])
            ->setColor($productSual['colors'])
            ->setGender($productSual['gender'])
            ->setBenefits($productSual['benefits'])
            ->setHowtouse($productSual['howtouse'])
            ->setIngredients($productSual['ingredients'])
            ->setLabel($productSual['label'])
            /* Sual UX */
            ->setProductPageType("fullwidth")
            ->setProductImageSize("6");


        //Producto Normal
        if ($attributeSet == 9) {
            $product->setStockData(array(
                    'use_config_manage_stock' => 0, //'Use config settings' checkbox
                    'manage_stock' => 1, //manage stock
                    'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
                    'is_in_stock' => ($productSual['available'] > 0) ? 1 : 0, //Stock Availability
                    'qty' => $productSual['available'] //qty
                )
            );
        } else {
            //Servicio
            $product->setStockData(array(
                    'use_config_manage_stock' => 0, //'Use config settings' checkbox
                    'manage_stock' => 0, //manage stock
                    'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
                    'is_in_stock' => 1, //Stock Availability
                )
            );
        }

        //$this->output .=  "Insertando Atributos base";
    }

    public function insertProductSapAttributes(&$product, $productSual, $isUpdate = false, $attributeSet = 9)
    {


        $product->setBrandSap($productSual['brand'])
            ->setSubbrandSap($productSual['subbrand'])
            ->setNameSap($productSual['name'])
            ->setColorsSap($productSual['colors'])
            ->setColorNameSap($productSual['color_name'])
            ->setColorHexSap($productSual['color_hex'])
            ->setAttributeSap($productSual['attribute'])
            ->setBenefitsSap($productSual['benefits'])
            ->setCategorySap($productSual['category'])
            ->setSubcategorySap($productSual['subcategory'])
            ->setContainerTypeSap($productSual['container_type'])
            ->setDescriptionSap($productSual['description'])
            ->setGenderSapTypeSap($productSual['gender'])
            ->setHowtouseSap($productSual['howtouse'])
            ->setIdSap($productSual['id'])
            ->setIngredientsSap($productSual['ingredients'])
            ->setLineSap($productSual['line'])
            ->setPointsSap($productSual['points'])
            ->setSearchSap($productSual['search'])
            ->setSeoUrlSap($productSual['seo_url'])
            ->setSizesSap($productSual['sizes'])
            ->setSizeNameSap($productSual['size_name'])
            ->setTypeSap($productSual['type']);

        if ($isUpdate) {

            $actualPrice = $product->getPrice();
            $newPrice = $productSual['price'];

            Mage::log($productSual['sku'] . " -> {$actualPrice} -> {$newPrice}",null,'prices.log');

            if($newPrice < $actualPrice){

                $this->promos++;
                $percentage = 100 - intval(($newPrice * 100) / $actualPrice);
                $categories = array();

                Mage::log($productSual['sku'] . " has {$percentage}% disccount. Original Price {$actualPrice}, Special Price {$newPrice}",null,'promos.log');

                $categoryPercentage = $this->getPercentageCategory($percentage);

                $date = new Zend_Date(Mage::getModel('core/date')->timestamp());
                $today = $date->toString('YYYY-MM-dd');

                $date->addDay('1');
                $tomorrow = $date->toString('YYYY-MM-dd');

                $product->setSpecialPrice( $newPrice );

                $product->setSpecialFromDate($today);
                $product->setSpecialFromDateIsFormated(true);

                $product->setSpecialToDate($tomorrow);
                $product->setSpecialToDateIsFormated(true);


                if (!empty($product->getCategoryIds())) {
                    $categories = $product->getCategoryIds();
                }

                array_push($categories, $categoryPercentage);

                $product->setCategoryIds(array_unique($categories));

            }else{
                $product->setPrice($productSual['price']);
            }

        }
    }

    public function getPercentageCategory($percentage){
        foreach($this->promotionsArray as $promos){
            if($percentage >= ($promos['value'] - 5) && $percentage <= ($promos['value'] + 4)){
                return $promos['id'];
            }
        }
    }

    public function setupPromoCategories(){

        $parentPromoCategoryId = $this->categoryExist($this->promotionsParentCategory, $this->parentCategoryId);

        if (!empty($parentPromoCategoryId)) {

            $parentPromoCategory = Mage::getModel('catalog/category')->load($parentPromoCategoryId);
            $promoCategoryIds = $parentPromoCategory->getChildren();

            foreach(explode(',',$promoCategoryIds) as $subCatid)
            {
                Mage::getModel('catalog/category')->load($subCatid)->delete();
            }

            $parentPromoCategory->delete();
        }

        $parentPromoCategoryId = $this->insertCategory(
            $this->promotionsParentCategory['name'],
            $this->promotionsParentCategory['url'],
            $this->parentCategoryId,
            1,0
        );

        foreach($this->promotionsArray as $key => $singlePromo){

            if(empty($singlePromo['id'])){
                $singlePromoId = $this->insertCategory(
                    $singlePromo['name'],
                    $singlePromo['url'],
                    $parentPromoCategoryId,1,0
                );

                $this->promotionsArray[$key]['id'] =  $singlePromoId;
            }

        }

    }

    public function categorizeProduct(&$product, $productSual)
    {

        $parentCategory = $this->parentCategoryId;

        $category = $this->categoryExist($productSual['category'], $parentCategory);
        $subcategory = $this->categoryExist($productSual['subcategory'], $category);
        $line = $this->categoryExist($productSual['line'], $subcategory, true);

        $categories = array();

        if (!empty($product->getCategoryIds())) {
            $categories = $product->getCategoryIds();
        }

        if (!empty($category))
            array_push($categories, $category);

        if (!empty($subcategory))
            array_push($categories, $subcategory);

        if (!empty($line))
            array_push($categories, $line);

        $product->setCategoryIds(array_unique($categories));

    }

    public function insertProduct($productSual, $attributeSet = 9)
    {

        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $product = Mage::getModel('catalog/product');

        $productExists = $this->productExists($productSual['sku']);

        try {

            if (!$productExists) {
                $urlImage = "https://www.sualbeauty.mx/img/" . $productSual['image'];
                $imageExists = $this->imageExists($urlImage);

                if (!$imageExists)
                    return;

                $this->insertProductBaseAttributes($product, $productSual, $urlImage, $attributeSet);
                $this->insertProductSapAttributes($product, $productSual, false, $attributeSet);

                if($attributeSet == 9)
                   $this->categorizeProduct($product, $productSual);

                $product->save();
                $this->insertados++;
                return $product;
            } else {
                $this->insertProductSapAttributes($productExists, $productSual, true, $attributeSet);
                //$this->categorizeProduct($productExists, $productSual);
                $productExists->save();
                $this->actualizados++;
                return $productExists;
            }

        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }

    public function insertCategory($name, $url, $parentCategoryId, $isActive = 1, $includedInMenu = 1)
    {
        $categoryMagento = Mage::getModel('catalog/category');
        $categoryMagento->setName($name);
        $categoryMagento->setNameSap($name);
        $categoryMagento->setUrlKey($url);
        $categoryMagento->setIsActive($isActive);
        $categoryMagento->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT);
        $categoryMagento->setIsAnchor(1); //for active achor
        $categoryMagento->setStoreId(Mage::app()->getStore()->getId());
        $categoryMagento->setIncludeInMenu($includedInMenu);
        $parentCategory = Mage::getModel('catalog/category')->load($parentCategoryId);
        $categoryMagento->setPath($parentCategory->getPath());
        $categoryMagento->save();
        $categoryId = $categoryMagento->getId();
        return $categoryId;
    }

    function addAttributeValue($attributeCode, $attValue)
    {

        $idAttribute = $this->attributeValueExists($attributeCode, $attValue);
        if (!$idAttribute && (!empty(trim($attValue) && !empty(trim($attributeCode))))) {
            $attr_model = Mage::getModel('catalog/resource_eav_attribute');
            $attr = $attr_model->loadByCode('catalog_product', $attributeCode);
            $attr_id = $attr->getAttributeId();
            $option['attribute_id'] = $attr_id;
            $option['value']['option_name'][0] = $attValue;
            $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
            $setup->addAttributeOption($option);

            $this->addAttributeValue($attributeCode, $attValue);
        } else {
            return $idAttribute;
        }
    }

    function attributeValueExists($attribute, $value)
    {
        $attribute_model = Mage::getModel('eav/entity_attribute');
        $attribute_options_model = Mage::getModel('eav/entity_attribute_source_table');
        $attribute_code = $attribute_model->getIdByCode('catalog_product', $attribute);
        $attribute = $attribute_model->load($attribute_code);
        $attribute_options_model->setAttribute($attribute);
        $options = $attribute_options_model->getAllOptions(false);

        foreach ($options as $option) {
            if ($option['label'] == $value) {
                return $option['value'];
            }
        }
        return false;
    }

    function utf8_converter($array)
    {
        array_walk_recursive($array, function (&$item, $key) {
            if (!mb_detect_encoding($item, 'utf-8', true)) {
                $item = utf8_encode($item);
            }
        });

        return $array;
    }
}