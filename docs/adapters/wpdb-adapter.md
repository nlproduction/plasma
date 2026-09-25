# WordPress adapter

```php
use Plasma\Plasma;
use Plasma\WordPress\WpdbAdapter;

$db = new Plasma(new WpdbAdapter('myplugin_'));
$db->registerSchema($dataSchemaArray);
```

`WpdbAdapter($tablePrefix = '', $wpdbInstance = null)` uses an injected wpdb-compatible instance or global `$wpdb`. The physical prefix is `$wpdb->prefix` plus the supplied suffix. No plugin-specific constants are read by the library.

Use separate clients for core tables and plugin tables. Core WordPress tables often use primary keys such as `ID`; supply these explicitly. Multisite users/usermeta may live under `$wpdb->base_prefix`, not the current site's prefix. Use the appropriate connection/prefix mapping; do not assume one prefix covers every multisite table.

`Plasma\WordPress\DB::configure($sources, $prefix)` and `DB::get()` offer an optional global convenience singleton. Prefer explicit Plasma instances in reusable plugins so multiple consumers cannot overwrite each other's configuration.

The adapter reports wpdb errors as exceptions instead of returning empty successful results. Nested transactions use savepoints and preserve their depth if a command fails. Transaction commands require a transactional database engine.

## Important WordPress boundary

Direct ORM access does not call WordPress entity APIs. Writes to posts/users/options do not automatically invoke the corresponding WordPress hooks, cache invalidation, capability checks, sanitization, or multisite rules. Prefer WordPress APIs for core-entity writes. Read access also requires your application's authorization.

## Shipping a plugin

Run Composer in development/build infrastructure and package production dependencies with your plugin. WordPress users do not need Node.js or Composer. Do not ship database credentials, development dependencies, or authentication files. Consider dependency namespace isolation if independently versioned plugins ship the same library.
