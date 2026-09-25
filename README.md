# Plasma

### Prisma-style database access for PHP and WordPress.

Query your database with readable PHP arrays instead of assembling SQL strings. Use the same query shape in a WordPress plugin, a standalone PHP application, or a visual query-builder interface.

**WordPress without SQL boilerplate.** Plasma ships with the WordPress core schema, so ordinary ORM queries against posts, users, metadata, comments, taxonomy, and options do not need hand-written `esc_sql()`, `$wpdb->prepare()`, placeholder juggling, or table-prefix concatenation. Plasma validates schema fields and identifiers, prepares values through the active adapter, escapes LIKE patterns, casts typed fields, and follows declared relations for you.

**Built by the developers of [MapSVG](https://mapsvg.com). Released under the [MIT license](LICENSE).**

```php
$products = $db->product->findMany([
    'where' => [
        'active' => true,
        'OR' => [
            ['price' => ['lt' => 50]],
            ['name' => ['contains' => 'coffee']],
        ],
    ],
    'include' => [
        'category' => [
            'select' => ['id', 'name'],
        ],
    ],
    'orderBy' => ['name' => 'asc'],
    'take' => 20,
]);
```

## Why Plasma?

- **Forget WordPress SQL boilerplate.** For supported ORM queries, Plasma handles `$wpdb->prepare()`, LIKE escaping, identifier validation, and prefixes internally instead of spreading `esc_sql()`, `%s`, and string-built SQL throughout your plugin.
- **WordPress core schema included.** `post`, `user`, `postmeta`, `comment`, taxonomy, options, and their relations are available automatically with `WpdbAdapter`; primary keys and field types are already known.
- **Schema-aware by default.** Schema-backed models reject unknown fields in filters, projections, sorting, and writes before SQL reaches the database.
- **An API you can read.** `findMany`, `findFirst`, `findUnique`, `create`, `createMany`, `upsertMany`, `update`, `delete`, `count`, and `distinct`.
- **Filters that travel.** Nested `AND` / `OR` / `NOT` and scalar operators are JSON-serializable, which makes saved queries and visual query builders straightforward.
- **Relations without N+1.** `include` loads `hasMany`, `hasOne`, and `belongsTo` relations in batches, with nested `select` for projections.
- **Bounded bulk writes and distinct values.** Insert or upsert homogeneous batches without per-row readback, and request schema-validated distinct scalars or tuples.
- **Transactions built in.** Run atomic units of work with automatic commit/rollback; nested transactions use savepoints so inner failures can roll back without discarding outer work.
- **Custom models at runtime.** Register schema metadata from a PHP array or JSON for your own tables; UI/form metadata can remain a separate application concern.
- **PDO and WordPress adapters.** Use the existing `$wpdb` connection in WordPress or PDO for MySQL/MariaDB and SQLite.
- **MIT and framework-independent.** Runtime supports PHP 7.4+; development/test tooling uses PHP 8.2+.

## Install

Install from the public GitHub repository through Composer:

```bash
composer config repositories.plasma vcs https://github.com/nlproduction/plasma
composer require nlproduction/plasma:^0.3
```

The VCS repository entry is required until the package is listed on Packagist. End users of a packaged WordPress plugin do not need Composer: run it during your build and ship the production `vendor/` directory.

## Start with a table

```php
require 'vendor/autoload.php';

use Plasma\Adapter\PdoAdapter;
use Plasma\Plasma;

$db = new Plasma(new PdoAdapter([
    'dsn' => 'mysql:host=localhost;dbname=shop;charset=utf8mb4',
    'username' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'prefix' => 'app_',
]));

// Reads app_products. Existing physical tables stay exactly as they are.
$products = $db->table('products');
$product = $products->create(['data' => ['name' => 'Coffee', 'price' => 12]]);
$products->update(['where' => ['id' => $product['id']], 'data' => ['price' => 15]]);
$products->delete(['where' => ['id' => $product['id']]]);
```

Plasma does not create or migrate tables. Use your application's migration system.

## Bulk writes and distinct values

```php
$processed = $products->createMany([
    'data' => [
        ['sku' => 'A-1', 'name' => 'Coffee', 'active' => true],
        ['name' => 'Tea', 'active' => false, 'sku' => 'B-2'],
    ],
]);

$processed = $products->upsertMany([
    'data' => [
        ['sku' => 'A-1', 'name' => 'Coffee beans', 'active' => true],
        ['sku' => 'C-3', 'name' => 'Cocoa', 'active' => true],
    ],
    'conflictFields' => ['sku'],
    'updateFields' => ['name', 'active'],
]);

$activeValues = $products->distinct(
    ['active'],
    ['orderBy' => ['active' => 'asc']]
);
```

Bulk rows must be a homogeneous list with the same columns; key order may differ. Batches are limited to 1,000 rows and return the number of input rows processed after successful execution, not a dialect-specific affected-row count. Schema casting and field allowlists still apply. MySQL/MariaDB upserts rely on database unique keys; SQLite uses the declared `conflictFields` target. A one-field `distinct()` returns scalar values, while multiple fields return associative tuples. [Full query and write contract →](docs/query-builder.md)

## Transactions

Wrap a unit of work in `transaction()`. Plasma commits when the callback returns and rolls back automatically if the callback throws an `Exception`, `Error`, or other `Throwable`:

```php
$order = $db->transaction(function (Plasma $db) {
    $order = $db->order->create([
        'data' => [
            'customer_id' => 42,
            'status' => 'pending',
        ],
    ]);

    $db->inventory->update([
        'where' => ['product_id' => 7],
        'data' => ['reserved' => 1],
    ]);

    return $order;
});
```

If the inventory update fails, the order insert is rolled back too.

### Nested transactions

Nested `transaction()` calls use database savepoints. An inner transaction can fail and roll back its own work while the outer transaction continues:

```php
$db->transaction(function (Plasma $db) {
    $db->audit->create(['data' => ['message' => 'Checkout started']]);

    try {
        $db->transaction(function (Plasma $db) {
            $db->payment->create([
                'data' => ['order_id' => 1001, 'status' => 'authorizing'],
            ]);

            throw new RuntimeException('Payment provider rejected the charge');
        });
    } catch (RuntimeException $e) {
        // The payment savepoint was rolled back.
        // The outer transaction is still alive.
        $db->audit->create(['data' => ['message' => 'Payment failed']]);
    }
});
```

You can also control the transaction manually when needed:

```php
$db->beginTransaction();

try {
    $db->product->update([
        'where' => ['id' => 7],
        'data' => ['active' => false],
    ]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
```

Both PDO and WordPress adapters support nested transaction depth with savepoints. Your database/table engine must support transactions; do not assume DDL statements such as `ALTER TABLE` are transactional. [Adapter transaction details →](docs/adapters/pdo-adapter.md#transactions)

## WordPress

WordPress core metadata is bundled and loaded automatically by `WpdbAdapter`:

```php
use Plasma\Plasma;
use Plasma\WordPress\WpdbAdapter;

$db = new Plasma(new WpdbAdapter());

$posts = $db->post->findMany([
    'where' => [
        'post_status' => 'publish',
        'post_type' => 'post',
    ],
    'include' => [
        'author' => [
            'select' => ['ID', 'display_name'],
        ],
    ],
    'orderBy' => ['post_date' => 'desc'],
    'take' => 10,
]);
```

No schema path or explicit `ID` primary-key configuration is needed for WordPress core tables. The bundled schema covers users/usermeta, posts/postmeta, comments/commentmeta, terms/taxonomy/relationships/meta, and options.

For plugin-owned tables, add a suffix and register your own models. WordPress core models still resolve against the site's normal `wp_` prefix while your plugin models use `wp_myplugin_`:

```php
$db = new Plasma(new WpdbAdapter('myplugin_'));
$db->registerSchema($myPluginSchema);

$products = $db->product->findMany(['where' => ['active' => true]]);
$posts = $db->post->findMany(['where' => ['post_status' => 'publish']]);
```

Plasma does not replace WordPress permissions, hooks, entity APIs, or cache invalidation. Prefer WordPress APIs for core-entity writes when those lifecycle semantics matter. [WordPress guide →](docs/adapters/wpdb-adapter.md)

## Model metadata — from PHP or JSON

```php
$db->registerSchema([
    'product' => [
        'table' => 'products',
        'primaryKey' => 'id',
        'fields' => [
            'id' => ['type' => 'int'],
            'name' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
            'settings' => ['type' => 'json'],
        ],
    ],
]);

$products = $db->product->findMany(['where' => ['active' => true]]);
```

Generate this array from your application's data-source settings, or pass files to `new Plasma($adapter, [$schemaFile])`. Keep form controls, labels, layouts, and other UI metadata outside the data schema. [Schema and relations →](docs/schema.md)

## From a visual query builder to PHP

[React Query Builder](https://react-querybuilder.js.org/docs/utils/export#prisma-orm) can export a Prisma-style `where` object:

```ts
const where = formatQuery(query, 'prisma');
// Send JSON.stringify({ where }) to your application's authenticated endpoint.
```

After authentication/authorization and selecting an allowed model, your PHP endpoint can pass the supported filter subset to Plasma. Schema-backed models validate field names before compiling SQL:

```php
$rows = $db->product->findMany([
    'where' => $validatedWhere,
    'take' => 50,
]);
```

Plasma is **Prisma-inspired, not a drop-in Prisma Client**. Relation filters, nested writes, aggregate functions such as sum/average, JSON-path filters, field-to-field comparisons, and Prisma migrations are not implemented. [Supported query contract →](docs/query-builder.md)

## Documentation

[Quick start](docs/getting-started.md) · [Queries](docs/query-builder.md) · [Schemas & relations](docs/schema.md) · [WordPress](docs/adapters/wpdb-adapter.md) · [PDO](docs/adapters/pdo-adapter.md) · [Events](docs/events/overview.md) · [Testing](docs/testing.md)

## Status and security

Early public release, with API changes still possible before 1.0. Query values are escaped by the selected adapter; ordinary PDO writes use bound parameters and bounded bulk statements use the adapter formatter. Identifiers, operators, pagination, batch size, and filter complexity are checked. Database failures raise exceptions instead of looking like empty results.

Those checks are **not an authorization layer**. Never expose an unrestricted database client to browser-supplied table names or query objects. See [SECURITY.md](SECURITY.md) for the trust boundary and supported behavior.

## Contributing

Bug reports with a reproducible query, failing tests, and focused pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

Made by the developers of **[MapSVG — interactive maps for WordPress](https://mapsvg.com)**. MIT licensed; use it in commercial and open-source projects.
