# Testing

Use PHP 8.2+ for Pest. The default integration suite uses SQLite when `pdo_sqlite` is installed. An external MySQL/MariaDB run uses `PLASMA_TEST_DSN`, `PLASMA_TEST_USER`, and `PLASMA_TEST_PASSWORD`; randomly prefixed tables are created and removed by the fixture.

```bash
composer install
composer test
composer validate --strict
php examples/sqlite.php
```

Use only a disposable database for external tests, never a customer or production DSN. Connection failures are not silently skipped when an external DSN is configured. The same database suite covers ordinary CRUD, transactions, relations, dedicated statement execution, bounded bulk insert/upsert, typed distinct results, and event-decorator behavior.

## Optional real wpdb integration

The normal WordPress adapter suite uses a mock `$wpdb`. A separate-process integration test can exercise the real WordPress `wpdb` class against disposable MySQL:

- `PLASMA_TEST_WP_ROOT` — WordPress root containing `wp-includes/class-wpdb.php`;
- `PLASMA_TEST_WP_CONFIG` — readable test config defining DB credentials;
- `PLASMA_TEST_DB_NAME` — a database name containing a standalone `test`, `testing`, or `ci` marker;
- `PLASMA_TEST_DB_DISPOSABLE=1` — explicit destructive-test opt-in.

The database must also contain `plasma_contract_fixture_marker`. The fixture creates only a randomly prefixed table, removes it in `finally`, and leaves the marker intact. Without all variables, the real-wpdb test is intentionally skipped.

PHP 7.4 syntax compatibility is checked by parsing every file under `src/` with the PHP 7.4 grammar. `php tests/runtime.php` is the dependency-free runtime smoke and can additionally be run under an actual PHP 7.4 binary. It accepts the same `PLASMA_TEST_DSN` variables or falls back to in-memory SQLite. Consumers can install the runtime library on PHP 7.4; developing the repository itself requires PHP 8.2+ because Composer resolves Pest development dependencies.

Optional Clockwork dependency installation is not needed for core tests. Disabled-listener behavior is tested; this is not a full compatibility test of the external Clockwork package.
