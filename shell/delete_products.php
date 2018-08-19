<?php

require_once 'abstract.php';


class Mage_Shell_DeleteProducts extends Mage_Shell_Abstract
{

    public function run()
    {
        Mage::app('admin')->setUseSessionInUrl(false);

        $products = Mage::getModel('catalog/product')->getCollection();
        foreach ($products as $product) {
            try {
                $product->delete();
            } catch (Exception $e) {
                echo "Product #" . $product->getId() . " could not be remvoved: " . $e->getMessage();

            }
        }


    }
}

$shell = new Mage_Shell_DeleteProducts();
$shell->run();