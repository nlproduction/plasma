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

$dsn = getenv('PLASMA_TEST_DSN') ?: 'sqlite::memory:';
$prefix = 'runtime_' . bin2hex(random_bytes(4)) . '_';
$base = new PdoAdapter([
    'dsn' => $dsn,
    'username' => getenv('PLASMA_TEST_USER') ?: '',
    'password' => getenv('PLASMA_TEST_PASSWORD') ?: '',
    'prefix' => $prefix,
]);

$table = $prefix . 'items';
if ($base->getDialect() === 'sqlite') {
    $base->execute(
        'CREATE TABLE ' . $table
        . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER)'
    );
} else {
    $base->execute(
        'CREATE TABLE ' . $table
        . ' (id INT UNSIGNED NOT NULL AUTO_INCREMENT,'
        . ' name VARCHAR(255) NOT NULL, active TINYINT(1) NOT NULL,'
        . ' PRIMARY KEY (id)) ENGINE=InnoDB'
    );
}

try {
    $db = (new Plasma(new EventDatabaseAdapter($base)))->registerSchema([
        'item' => [
            'table' => 'items',
            'fields' => [
                'id' => ['type' => 'int'],
                'name' => ['type' => 'string'],
                'active' => ['type' => 'boolean'],
            ],
        ],
    ]);

    $db->item->create(['name' => 'literal %s', 'active' => false]);
    $db->item->createMany([
        ['name' => 'bulk one', 'active' => true],
        ['active' => false, 'name' => 'bulk two'],
    ]);

    $row = $db->item->findFirst([
        'where' => ['name' => ['contains' => '%s']],
    ]);
    if ($row['name'] !== 'literal %s' || $row['active'] !== false) {
        throw new RuntimeException('Runtime query/casting check failed.');
    }
    if ($db->item->count() !== 3) {
        throw new RuntimeException('Runtime bulk-write check failed.');
    }
    if ($db->item->distinct(['active'], ['orderBy' => ['active' => 'asc']]) !== [false, true]) {
        throw new RuntimeException('Runtime distinct check failed.');
    }

    try {
        $db->transaction(function (Plasma $db) {
            $db->item->create(['name' => 'rollback', 'active' => true]);
            throw new Error('expected rollback');
        });
    } catch (Error $error) {
        if ($error->getMessage() !== 'expected rollback') { throw $error; }
    }

    if ($db->item->count() !== 3 || $base->inTransaction()) {
        throw new RuntimeException('Runtime transaction check failed.');
    }

    echo 'Runtime smoke passed on PHP ' . PHP_VERSION
        . ' using ' . $base->getDialect() . PHP_EOL;
} finally {
    $base->execute('DROP TABLE IF EXISTS ' . $table);
}
