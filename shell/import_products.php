<?php

require_once 'abstract.php';


class Mage_Shell_ImportProduct extends Mage_Shell_Abstract
{

    public function run()
    {
        $this->importCategories();
        //$this->importProducts();

    }

    public function getUrl($name)
    {

        $find = array("Á", "É", "Í", "Ó", "Ú", "Ñ", "&");
        $replace = array("A", "E", "I", "O", "Ú", "N", "Y");
        return str_replace(' ', '-', strtolower(str_replace($find, $replace, ($name))));
    }


    public function categoryExist($childCategoryName, $parentCategoryId)
    {

        $parentCategory = Mage::getModel('catalog/category')->load($parentCategoryId);
        $childCategory = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToFilter('is_active', true)
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

        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $newCategories = $connection->query('SELECT category FROM sb_product WHERE category IS NOT NULL GROUP BY category');

        $parentCategoryId = Mage::getModel('catalog/category')
            ->getCollection()
            ->setStoreId()
            ->addAttributeToFilter('name', 'SUAL')->getFirstItem()->getId();


        foreach ($newCategories as $category) {

            $name = $category['category'];
            $url = $this->getUrl($name);

            $categoryId = $this->categoryExist($name, $parentCategoryId);

            if (!$categoryId) {
                echo "Insertando Categoria {$category['category']} URL {$url}\n";
                $categoryId = $this->insertCategory($name, $url, $parentCategoryId);
            }

            echo $categoryId . " CATEGORIA PADRE - \n";


            $subcategories = $connection->query(" SELECT subcategory
                                                    FROM sb_product
                                                  WHERE category = '{$category['category']}' AND subcategory IS NOT NULL AND subcategory <> ''
                                                    GROUP BY subcategory");

            foreach ($subcategories as $subcategory) {

                $nameSub = $subcategory['subcategory'];
                $urlSub = $this->getUrl($nameSub);

                $subcategoryId = $this->categoryExist($nameSub, $categoryId);

                if (!$subcategoryId) {
                    echo "\tInsertando subcategoria {$subcategory['subcategory']} URL -> {$urlSub}\n";
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
                        echo "\t\tInsertando linea {$lineId['line']} URL -> {$urlLine}\n";
                        //$this->insertCategory($nameLine, $urlLine, $subcategoryId);

                    }

                }

            }

        }

    }

    public function importProducts()
    {
        echo "Iniciando";
        $where = ' WHERE type = "PRODUCTO" OR type = "OBSEQUIO"';
        $new_db_resource = Mage::getSingleton('core/resource');
        $connection = $new_db_resource->getConnection('import_db');
        $howmanyProducts = $connection->query('SELECT count(*) as howmany FROM sb_product' . $where);

        foreach ($howmanyProducts as $many) {
            print_r($many);
        }
        $results = $connection->query('SELECT *  FROM sb_product' . $where);

        foreach ($results as $result) {
            print_r($result['sku'] . "\n");
            die;
        }
    }



    public function insertCategory($name, $url, $parentCategoryId)
    {
        $categoryMagento = Mage::getModel('catalog/category');
        $categoryMagento->setName($name);
        $categoryMagento->setUrlKey($url);
        $categoryMagento->setIsActive(1);
        $categoryMagento->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT);
        $categoryMagento->setIsAnchor(1); //for active achor
        $categoryMagento->setStoreId(Mage::app()->getStore()->getId());
        $parentCategory = Mage::getModel('catalog/category')->load($parentCategoryId);
        $categoryMagento->setPath($parentCategory->getPath());
        $categoryMagento->save();
        $categoryId = $categoryMagento->getId();
        return $categoryId;
    }

}


$shell = new Mage_Shell_ImportProduct();
$shell->run();