<?php
/**
* @category   Entrepids
* @package    Entrepids_Completeaddress
* @author     miguel.perez@entrepids.com
* @website    http://www.entrepids.com
*/
$installer = $this;
  
$installer->startSetup();
  
$installer->run("
  
DROP TABLE IF EXISTS {$this->getTable('directory_region_neighborhood')};
CREATE TABLE {$this->getTable('directory_region_neighborhood')} (
  `neighborhood_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `postal_code` int(10) UNSIGNED NOT NULL,
  `region_id` int(10) UNSIGNED NOT NULL,
  `neighborhood` varchar(70) NOT NULL,
  `municipality` varchar(50) NOT NULL,
  PRIMARY KEY (`neighborhood_id`),
  KEY `IDX_DIRECTORY_REGION_NEIGHBORHOOD_REGION_ID` (`region_id`),
  KEY `IDX_DIRECTORY_REGION_NEIGHBORHOOD_POSTALCODE` (`postal_code`),
  CONSTRAINT `FK_REGION_NEIGHBORHOOD_REGION_ID` FOREIGN KEY (`region_id`) REFERENCES `directory_country_region` (`region_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
  
$installer->endSetup(); 