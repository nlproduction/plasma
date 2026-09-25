<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Plasma\Adapter\PdoAdapter;
use Plasma\Plasma;

$adapter = new PdoAdapter(['dsn' => 'sqlite::memory:', 'prefix' => 'demo_']);
$adapter->query('CREATE TABLE demo_products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, price INTEGER, active INTEGER)');
$db = (new Plasma($adapter))->registerSchema([
    'product' => [
        'table' => 'products',
        'fields' => ['id' => ['type' => 'int'], 'active' => ['type' => 'boolean']],
    ],
]);
$db->transaction(function (Plasma $db) {
    $db->product->create(['data' => ['name' => 'Coffee', 'price' => 12, 'active' => true]]);
    $db->product->create(['data' => ['name' => 'Tea', 'price' => 8, 'active' => true]]);
});
$rows = $db->product->findMany([
    'where' => ['active' => true, 'OR' => [['price' => ['lt' => 10]], ['name' => ['contains' => 'Coffee']]]],
    'orderBy' => ['price' => 'asc'],
]);
if (array_column($rows, 'name') !== ['Tea', 'Coffee']) {
    throw new RuntimeException('Example returned unexpected rows.');
}
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
