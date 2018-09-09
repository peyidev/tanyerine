<?php
/**
 * Plumrocket Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the End-user License Agreement
 * that is available through the world-wide-web at this URL:
 * http://wiki.plumrocket.net/wiki/EULA
 * If you are unable to obtain it through the world-wide-web, please
 * send an email to support@plumrocket.com so we can send you a copy immediately.
 *
 * @package     Plumrocket_SocialLogin
 * @copyright   Copyright (c) 2014 Plumrocket Inc. (http://www.plumrocket.com)
 * @license     http://wiki.plumrocket.net/wiki/EULA  End-user License Agreement
 */


$installer = $this;

$installer->startSetup();

/**
 * Create table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('pslogin/account'))
    ->addColumn(
        'id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        ), 'Id'
    )
    ->addColumn(
        'type', Varien_Db_Ddl_Table::TYPE_CHAR, 30, array(
        'nullable'  => false,
        ), 'Login type'
    )

    ->addColumn(
        'user_id', Varien_Db_Ddl_Table::TYPE_CHAR, 255, array(
        'nullable'  => false,
        ), 'User Id'
    )
    ->addColumn(
        'customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
        ), 'Customer Id'
    )
    ->addIndex(
        $installer->getIdxName('pslogin/account', array('type')),
        array('type')
    )
    ->addIndex(
        $installer->getIdxName('pslogin/account', array('user_id')),
        array('user_id')
    )
    ->addIndex(
        $installer->getIdxName('pslogin/account', array('customer_id')),
        array('customer_id')
    );

    /* Fix - check table engine before setting foreign key */
    $dbName = (string)Mage::getConfig()->getNode('global/resources/default_setup/connection/dbname');
    $readResource = $installer->getConnection('core_read');
    $query = $readResource ->select()
        ->from('information_schema.TABLES', 'ENGINE')
        ->where('TABLE_SCHEMA=?', $dbName)
        ->where('TABLE_NAME=?', $installer->getTable('customer/entity'));

    $tableEngine = $readResource->fetchOne($query);

    if (strtolower($tableEngine) != 'myisam') {
        $table->addForeignKey(
            $installer->getFkName('pslogin/account', 'customer_id', 'customer/entity', 'entity_id'),
            'customer_id', $installer->getTable('customer/entity'), 'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        );
    }
    /* End fix */


$table->setComment('Social Login Customer');
$installer->getConnection()->createTable($table);

$installer->endSetup();