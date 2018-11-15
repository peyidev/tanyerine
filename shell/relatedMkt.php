<?php

require_once 'abstract.php';
ini_set('display_errors', 1);
ini_set("memory_limit", -1);


class Mage_Shell_Related_Mkt extends Mage_Shell_Abstract
{

    public $productCollection;
    public $relatedCache = array();

    public function run()
    {

        $limit = 20;
        $howmany = 0;

        $this->productCollection = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('*');

        //Crossale misma marca diferente linea
        //Upsale misma marca

        foreach($this->productCollection as $product){

            echo "Product: " . $product->getSku() . "\n";
            $line = $product->getLineSap();
            $brand = $product->getBrand();

            $byLine = $this->getProductsByAttribute('line_sap',$line);
            $byBrand = $this->getProductsByAttribute('brand',$brand);

            $crossSellProductsArranged = $this->getNewCrossSellProducts($byBrand, $byLine);
            $upSellProductsArranged = $this->getNewUpsaleProducts($byBrand, $product->getId());

            $product->setUpSellLinkData($upSellProductsArranged);
            $product->setCrossSellLinkData($crossSellProductsArranged);
            $product->save();

            $howmany++;

            if($howmany >= $limit)
                break;
        }
        //

    }

    public function getNewUpsaleProducts($brandArray, $actualProduct){

        $result = array();
        $actualProductArray = array($actualProduct);
        $intersect = array_diff($brandArray, $actualProductArray);

        foreach($intersect as $productId){
            $result[$productId] = array('position' => '');
        }
        return $result;
    }

    public function getNewCrossSellProducts($brandArray, $lineArray){

        $result = array();
        $intersect = array_diff($brandArray, $lineArray);

        foreach($intersect as $productId){
            $result[$productId] = array('position' => '');
        }

        return $result;

    }

    public function getProductsByAttribute($attr, $query){

        $hash = md5($query);
        if(!empty($this->relatedCache[$attr][$hash]))
            return $this->relatedCache[$attr][$hash];

        $result = array();
        $lined = Mage::getModel('catalog/product')->getCollection()->addAttributeToFilter($attr, array('eq' => $query));

        foreach ($lined as $product){
            $result[] = $product->getId();
        }
        $this->relatedCache[$attr][$hash] = $result;
        return $this->relatedCache[$attr][$hash];
    }

}


$shell = new Mage_Shell_Related_Mkt();
$shell->run();
die();