# Plasma

### Prisma-style queries. Plain PHP. No framework required.

Query your database with readable PHP arrays instead of assembling SQL strings. Use the same query shape in a WordPress plugin, a standalone PHP application, or a visual query-builder interface.

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
    'orderBy' => ['name' => 'asc'],
    'take' => 20,
]);
```

**No Node.js. No code generation. No application framework.** Start with existing tables; add model metadata only when you need casting or relationships.

## Why Plasma?

- **An API you can read.** `findMany`, `findFirst`, `findUnique`, `create`, `update`, `delete`, and `count`.
- **Filters that travel.** Nested `AND` / `OR` / `NOT` and scalar operators are JSON-serializable. Useful for saved queries and visual query builders.
- **WordPress without a second connection.** Use the existing `$wpdb`, including custom prefixes. PDO supports MySQL/MariaDB and SQLite.
- **Optional, runtime-defined models.** Register a PHP array or load JSON. No writable schema files or Node runtime needed on customer servers.
- **Useful ORM essentials.** Batched relationship loading, JSON/boolean casting, large-integer preservation, and nested transactions with savepoints.
- **No mandatory framework dependencies.** PHP 7.4+ and JSON; the database extension depends on your adapter. Optional event listeners and Clockwork integration.

## Install

Install from the public GitHub repository through Composer:

```bash
composer config repositories.plasma vcs https://github.com/nlproduction/plasma
composer require nlproduction/plasma:^0.1
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

## WordPress

```php
use Plasma\Plasma;
use Plasma\WordPress\WpdbAdapter;

// Site prefix + your plugin prefix: wp_myplugin_products.
$db = new Plasma(new WpdbAdapter('myplugin_'));
$products = $db->table('products')->findMany(['take' => 20]);

// Core WordPress tables: no plugin suffix, and an explicit primary key.
$wp = new Plasma(new WpdbAdapter());
$posts = $wp->table('posts', 'ID')->findMany([
    'where' => ['post_status' => 'publish'],
    'take' => 10,
]);
```

Prefer separate client instances when several plugins share the process. Plasma does not bundle WordPress table schemas or replace WordPress APIs, permissions, hooks, or cache invalidation. [WordPress guide →](docs/adapters/wpdb-adapter.md)

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

After authentication, authorization, table/field allowlisting, and payload validation, your PHP endpoint can pass the supported filter subset to Plasma:

```php
$rows = $db->product->findMany([
    'where' => $validatedWhere,
    'take' => 50,
]);
```

Plasma is **Prisma-inspired, not a drop-in Prisma Client**. Relation filters, nested writes, aggregations, JSON-path filters, field-to-field comparisons, and Prisma migrations are not implemented. [Supported query contract →](docs/query-builder.md)

## Documentation

[Quick start](docs/getting-started.md) · [Queries](docs/query-builder.md) · [Schemas & relations](docs/schema.md) · [WordPress](docs/adapters/wpdb-adapter.md) · [PDO](docs/adapters/pdo-adapter.md) · [Events](docs/events/overview.md) · [Testing](docs/testing.md)

## Status and security

Early public release, with API changes still possible before 1.0. Query values are escaped by the selected adapter; PDO writes use bound parameters. Identifiers, operators, pagination, and filter complexity are checked. Database failures raise exceptions instead of looking like empty results.

Those checks are **not an authorization layer**. Never expose an unrestricted database client to browser-supplied table names or query objects. See [SECURITY.md](SECURITY.md) for the trust boundary and supported behavior.

## Contributing

Bug reports with a reproducible query, failing tests, and focused pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

Made by the developers of **[MapSVG — interactive maps for WordPress](https://mapsvg.com)**. MIT licensed; use it in commercial and open-source projects.
