# Quick start

[Install with Composer](../README.md#install), then require `vendor/autoload.php`.

## A runnable SQLite example

Requires `pdo_sqlite`. This example creates its own in-memory tables; Plasma itself never creates tables automatically.

```php
use Plasma\Adapter\PdoAdapter;
use Plasma\Plasma;

$adapter = new PdoAdapter(['dsn' => 'sqlite::memory:']);
$adapter->query('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, price INTEGER)');
$db = new Plasma($adapter);
$db->products->create(['data' => ['name' => 'Coffee', 'price' => 12]]);
$rows = $db->products->findMany(['where' => ['price' => ['lt' => 20]]]);
```

The complete executable version is in [examples/sqlite.php](../examples/sqlite.php).

For [WordPress](adapters/wpdb-adapter.md), `WpdbAdapter` uses the existing `$wpdb` connection and automatically registers the bundled WordPress core schema. For [MySQL/MariaDB](adapters/pdo-adapter.md), supply a PDO DSN and credentials from environment configuration. Add [schema metadata](schema.md) for your own named models, field casting, field validation, or relations.
