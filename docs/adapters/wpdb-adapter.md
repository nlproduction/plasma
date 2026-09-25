# WordPress adapter

Plasma ships with metadata for the standard WordPress core tables. A `WpdbAdapter` supplies that schema automatically, so there is no schema path to configure for posts, users, metadata, comments, taxonomy, or options.

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
    'take' => 20,
]);
```

## Why this is useful in WordPress

For queries expressed through Plasma, plugin code does not need to manually assemble SQL, count `%s` placeholders, call `esc_sql()`, call `$wpdb->prepare()`, escape LIKE wildcards, or concatenate `$wpdb->prefix`.

Plasma's query compiler validates identifiers and operators. The schema-backed WordPress models additionally reject fields not present in the bundled schema. Values are prepared by `$wpdb`, LIKE input goes through `$wpdb->esc_like()`, and known field types are hydrated automatically.

That protection applies to Plasma's documented ORM API. Raw SQL passed to the adapter is still trusted application code.

## Included models

The bundled schema currently contains:

- `user` / `usermeta`
- `post` / `postmeta`
- `comment` / `commentmeta`
- `term` / `termtaxonomy` / `termrelationship` / `termmeta`
- `option`

Relations such as `post.author`, `post.comments`, `post.postmeta`, `user.posts`, and taxonomy relationships are available through `include`.

## Plugin tables and prefixes

Pass a suffix for plugin-owned tables:

```php
$db = new Plasma(new WpdbAdapter('myplugin_'));
$db->registerSchema($myPluginSchema);

// wp_myplugin_products
$products = $db->product->findMany();

// Still wp_posts, not wp_myplugin_posts
$posts = $db->post->findMany();
```

Core WordPress models are resolved as absolute WordPress tables, while dynamic/custom models use `$wpdb->prefix . $tablePrefix`.

On multisite, site tables use `$wpdb->prefix` and the bundled `user` / `usermeta` models use `$wpdb->base_prefix`.

## Convenience singleton

`Plasma\WordPress\DB::configure($sources, $prefix)` and `DB::get()` provide an optional singleton. Bundled WordPress metadata is still loaded automatically; `$sources` are only additional application schemas.

Prefer explicit `Plasma` instances in reusable plugins when several independent consumers may share one PHP process.
Wrapping `WpdbAdapter` with `EventDatabaseAdapter` remains transparent: the decorator forwards bundled WordPress schemas and reports the underlying MySQL dialect.

## Statements, errors, and transactions

Use `query()` for row-returning SQL and `execute()` for trusted DDL or other non-row statements. `execute()` delegates to `$wpdb->query()` and returns its integer result. DDL counts vary by WordPress/MySQL behavior, so treat the number as meaningful mainly for DML.

Schema-backed `createMany()`, `upsertMany()`, and `distinct()` use the same prefix, field validation, casting, preparation, and error boundary. MySQL upsert behavior is driven by actual primary and unique constraints.

The adapter reports wpdb failures as exceptions instead of turning them into empty successful results. Nested transactions use savepoints and preserve transaction depth if a command fails. Transaction commands require a transactional database engine.

## Important WordPress boundary

Direct ORM access does **not** call WordPress entity APIs. Writes to posts/users/options do not automatically invoke WordPress hooks, cache invalidation, capability checks, sanitization, or multisite lifecycle rules.

For core-entity writes, prefer WordPress APIs when those semantics matter. Read endpoints still need application authentication and authorization.

## Shipping a plugin

Run Composer during development/build and package production dependencies with your plugin. End users do not need Composer. Do not ship database credentials, development dependencies, or authentication files. Consider dependency namespace isolation if independently versioned plugins ship the same library.
