<?php
/**
 * Created by PhpStorm.
 * User: palberto
 * Date: 8/19/18
 * Time: 4:09 PM
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('sual_importer/importer_execute'))
    ->addColumn('importer_execute_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'auto_increment' => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Id')
    ->addColumn('tipo_ejecucion', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
        'nullable'  => false,
    ), 'Tipo de ejecucion')
    ->addColumn("inicio", Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        "default" => Varien_Db_Ddl_Table::TIMESTAMP_INIT
    ), "Inicio de ejecucion")
    ->addColumn("fin", Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => true,
    ), "Fin de ejecucion")
    ->addColumn('resumen', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
    ), 'Resumen de ejecucion');
$installer->getConnection()->createTable($table);


$installer->endSetup();
