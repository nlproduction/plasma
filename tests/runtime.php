<?php

// Dependency-free runtime smoke test, including PHP 7.4.
spl_autoload_register(function ($class) {
    if (strpos($class, 'Plasma\\') === 0) {
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) { require $file; }
    }
});

use Plasma\Adapter\PdoAdapter;
use Plasma\Adapter\EventDatabaseAdapter;
use Plasma\Plasma;

$base = new PdoAdapter(['dsn' => 'sqlite::memory:', 'prefix' => 'runtime_']);
$base->query('CREATE TABLE runtime_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER)');
$db = (new Plasma(new EventDatabaseAdapter($base)))->registerSchema([
    'item' => ['table' => 'items', 'fields' => ['id' => ['type' => 'int'], 'name' => ['type' => 'string'], 'active' => ['type' => 'boolean']]],
]);
$db->item->create(['name' => 'literal %s', 'active' => false]);
$row = $db->item->findFirst(['where' => ['name' => ['contains' => '%s']]]);
if ($row['name'] !== 'literal %s' || $row['active'] !== false || $db->item->count() !== 1) {
    throw new RuntimeException('Runtime query/casting check failed.');
}
try {
    $db->transaction(function (Plasma $db) {
        $db->item->create(['name' => 'rollback']);
        throw new Error('expected rollback');
    });
} catch (Error $error) {
    if ($error->getMessage() !== 'expected rollback') { throw $error; }
}
if ($db->item->count() !== 1 || $base->inTransaction()) {
    throw new RuntimeException('Runtime transaction check failed.');
}
echo 'Runtime smoke passed on PHP ' . PHP_VERSION . PHP_EOL;
