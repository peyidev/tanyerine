<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 8/19/18
 * Time: 4:48 PM
 */ 
class Sual_Importer_Helper_Data extends Mage_Core_Helper_Abstract {

    public $parentCategoryId = "";
    public $insertados = 0;
    public $actualizados = 0;
    public $output = "";


    public function execute($executionId, $source)
    {
        $this->output .= "Ejecutado desde <strong>{$source}</strong>\n";
        $this->parentCategoryId = Mage::getModel('catalog/category')
            ->getCollection()
            ->setStoreId()
            ->addAttributeToFilter('name', 'SUAL')->getFirstItem()->getId();

        $executionStartTime = microtime(true);
        $this->importCategories();
        $executionEndTime1 = microtime(true);
        $minutes = ($executionEndTime1 - $executionStartTime) / 60;
        $this->output .=  "<strong>importCategories</strong> tardó <span style='color:#F77812;'>$minutes</span> minutos en ejecutar.\n";

        $this->importProducts();
        $executionEndTime2 = microtime(true);
        $minutes = ($executionEndTime2 - $executionStartTime) / 60;
        $this->output .=  "<strong>importProducts</strong> tardó <span style='color:#F77812;'>$minutes</span> minutos en ejecutar.\n";

        $this->closeExecution($executionId);

    }

    public function closeExecution($executionId){

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
        $img = $sku . '-' .  strtoupper($this->getUrl($brand)) . '-' . $name . "." . $extension;
        $whereToSave = $base_path . '/media/imports/' . $img;

        if(!file_exists($whereToSave))
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

    public function categoryExist($childCategoryName, $parentCategoryId)
    {

        $parentCategory = Mage::getModel('catalog/category')->load($parentCategoryId);
        $childCategory = Mage::getModel('catalog/category')->getCollection()
            ->addIdFilter($parentCategory->getChildren())
            ->addAttributeToFilter('name', $childCategoryName)
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

            $name = $category['category'];
            $url = $this->getUrl($name);

            $categoryId = $this->categoryExist($name, $parentCategoryId);

            if (!$categoryId) {
                //$this->output .=  "Insertando Categoria {$category['category']} URL {$url}\n";
                $categoryId = $this->insertCategory($name, $url, $parentCategoryId);
            }

            //$this->output .=  $categoryId . " CATEGORIA PADRE - \n";


            $subcategories = $connection->query(" SELECT subcategory
                                                    FROM sb_product
                                                  WHERE category = '{$category['category']}' AND subcategory IS NOT NULL AND subcategory <> ''
                                                    GROUP BY subcategory");

            foreach ($subcategories as $subcategory) {

                $nameSub = $subcategory['subcategory'];
                $urlSub = $this->getUrl($nameSub);

                $subcategoryId = $this->categoryExist($nameSub, $categoryId);

                if (!$subcategoryId) {
                    //$this->output .=  "\tInsertando subcategoria {$subcategory['subcategory']} URL -> {$urlSub}\n";
                    $subcategoryId = $this->insertCategory($nameSub, $urlSub, $categoryId);
                }

                $lines = $connection->query(" SELECT line
                                                    FROM sb_product
                                                  WHERE category = '{$category['category']}' AND subcategory = '{$subcategory['subcategory']}'  AND line IS NOT NULL AND line <> ''
                                                    GROUP BY line");

                foreach ($lines as $line) {

                    continue;
                    //Remove this to insert lines
                    $nameLine = $line['line'];
                    $urlLine = $this->getUrl($nameLine);

                    $lineId = $this->categoryExist($nameLine, $subcategoryId);

                    if (!$lineId) {
                        //$this->output .=  "\t\tInsertando linea {$lineId['line']} URL -> {$urlLine}\n";
                        $this->insertCategory($nameLine, $urlLine, $subcategoryId, false);

                    }

                }

            }

        }

    }

    public function importProducts()
    {
        //$this->output .=  "Iniciando";

        $limit = 10;
        $limitSql = " LIMIT {$limit}";

        $where = ' WHERE type = "PRODUCTO" OR type = "OBSEQUIO"' . $limitSql;
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $howmanyProducts = $connection->query('SELECT count(*) as howmany FROM sb_product' . $where);
        $totalProducts = 0;

        foreach ($howmanyProducts as $many) {
            $totalProducts = $many;
        }

        $products = $connection->query('SELECT *  FROM sb_product' . $where);

        foreach ($products as $product) {
            $this->insertProduct($product);

           // if(!empty($product))
           //     echo "Productos insertados {$this->insertados} / actualizados {$this->actualizados}.\n";

        }

        if($limit > 0)
            $totalProducts = $limit;

        $this->output .=  "Se procesaron {$totalProducts['howmany']} productos.\n";
        $this->output .=  "Productos insertados {$this->insertados} / actualizados {$this->actualizados}.\n";
    }


    public function insertProductBaseAttributes(&$product, $productSual, $urlImage)
    {

        $idAttribute = $this->addAttributeValue('brand', $productSual['brand']);

        $product
            ->setWebsiteIds(array(1))//website ID the product is assigned to, as an array
            ->setAttributeSetId(9)//ID of a attribute set named 'default'
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

            ->setStockData(array(
                    'use_config_manage_stock' => 0, //'Use config settings' checkbox
                    'manage_stock' => 1, //manage stock
                    'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
                    'is_in_stock' => 1, //Stock Availability
                    'qty' => 10 //qty
                )
            )
            /* Sual eCom */
            ->setBrand($idAttribute)
            ->setAtribute($productSual['attribute'])
            ->setContainerType($productSual['container_type'])
            ->setColor($productSual['colors'])
            ->setGender($productSual['gender'])
            ->setBenefits($productSual['benefits'])
            ->setUse($productSual['use'])
            ->setHowToUse($productSual['howtouse'])
            ->setIngredients($productSual['ingredients'])
            ->setLabel($productSual['label'])
            /* Sual UX */
            ->setProductPageType("fullwidth")
            ->setProductImageSize("6");



        //$this->output .=  "Insertando Atributos base";
    }

    public function insertProductSapAttributes(&$product, $productSual)
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
            ->setHowTouseSap($productSual['howtouse'])
            ->setIdSap($productSual['id_sap'])
            ->setIngredientsSap($productSual['ingredients'])
            ->setLineSap($productSual['line'])
            ->setPointsSap($productSual['points'])
            ->setSearchSap($productSual['search'])
            ->setSeoUrlSap($productSual['seo_url'])
            ->setSizesSap($productSual['sizes'])
            ->setSizeNameSap($productSual['size_name'])
            ->setTypeSap($productSual['type']);
    }

    public function categorizeProduct(&$product, $productSual)
    {


        $parentCategory = $this->parentCategoryId;

        $category = $this->categoryExist($productSual['category'], $parentCategory);
        $subcategory = $this->categoryExist($productSual['subcategory'], $category);
        $line = $this->categoryExist($productSual['line'], $subcategory);

        $categories = array();


        if (!empty($category))
            array_push($categories, $category);

        if (!empty($subcategory))
            array_push($categories, $subcategory);

        if (!empty($line))
            array_push($categories, $line);


        $product->setCategoryIds($categories);

    }

    public function insertProduct($productSual)
    {


        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $product = Mage::getModel('catalog/product');

        $productExists = $this->productExists($productSual['sku']);

        try {

            if (!$productExists) {
                $urlImage = "https://www.sualbeauty.com/img/" . $productSual['image'];
                $imageExists = $this->imageExists($urlImage);

                if (!$imageExists)
                    return;

                $this->insertProductBaseAttributes($product, $productSual, $urlImage);
                $this->insertProductSapAttributes($product, $productSual);
                $this->categorizeProduct($product, $productSual);
                $product->save();
                $this->insertados++;
                return $product;
            } else {
                $this->insertProductSapAttributes($productExists, $productSual);
                $this->categorizeProduct($productExists, $productSual);
                $productExists->save();
                $this->actualizados++;
                return $productExists;
            }

        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }

    public function insertCategory($name, $url, $parentCategoryId, $isActive = 1)
    {
        $categoryMagento = Mage::getModel('catalog/category');
        $categoryMagento->setName($name);
        $categoryMagento->setUrlKey($url);
        $categoryMagento->setIsActive($isActive);
        $categoryMagento->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT);
        $categoryMagento->setIsAnchor(1); //for active achor
        $categoryMagento->setStoreId(Mage::app()->getStore()->getId());
        $parentCategory = Mage::getModel('catalog/category')->load($parentCategoryId);
        $categoryMagento->setPath($parentCategory->getPath());
        $categoryMagento->save();
        $categoryId = $categoryMagento->getId();
        return $categoryId;
    }

    function addAttributeValue($attributeCode, $attValue) {

        $idAttribute = $this->attributeValueExists($attributeCode, $attValue);
        if (!$idAttribute) {
            $attr_model = Mage::getModel('catalog/resource_eav_attribute');
            $attr = $attr_model->loadByCode('catalog_product', $attributeCode);
            $attr_id = $attr->getAttributeId();
            $option['attribute_id'] = $attr_id;
            $option['value']['option_name'][0] = $attValue;
            $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
            $setup->addAttributeOption($option);

            $this->addAttributeValue($attributeCode, $attValue);
        }else{
            return $idAttribute;
        }
    }

    function attributeValueExists($attribute, $value) {
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
}