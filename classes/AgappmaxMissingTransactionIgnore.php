<?php

class AgappmaxMissingTransactionIgnore extends AgObjectModel
{
    public static $definition = [
        'table' => 'agappmax_missing_transaction_ignore',
        'primary' => 'id_agappmax_missing_transaction_ignore',
        'multilang' => false,
        'fields' => [
            'id_agappmax_missing_transaction_ignore' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'db_type' => 'int unsigned', 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'db_type' => 'datetime', 'required' => true],
        ],
        'indexes' => [
            [
                'fields' => ['id_order'],
                'name' => 'uniq_agappmax_missing_transaction_ignore_order',
                'unique' => true,
            ],
            [
                'fields' => ['date_add'],
                'name' => 'idx_agappmax_missing_transaction_ignore_date_add',
            ],
        ],
    ];

    public $id_agappmax_missing_transaction_ignore;
    public $id_order;
    public $date_add;

    public static function getByOrderId($orderId)
    {
        $collection = new PrestaShopCollection('AgappmaxMissingTransactionIgnore');
        $collection->where('id_order', '=', (int) $orderId);

        return $collection->getFirst();
    }

    public static function getAll()
    {
        $collection = new PrestaShopCollection('AgappmaxMissingTransactionIgnore');

        return $collection->getResults();
    }
}