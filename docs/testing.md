# Testing

Use PHP 8.2+ for Pest and install `pdo_sqlite`. The default suite executes real SQL against isolated in-memory databases; WordPress boundary tests use a mock wpdb. No external database credentials are required.

```bash
composer install
composer test
composer validate --strict
php examples/sqlite.php
```

An optional external database run uses `PLASMA_TEST_DSN`, `PLASMA_TEST_USER`, and `PLASMA_TEST_PASSWORD`. The database integration fixture creates and drops randomly prefixed tables. **Use only a disposable database**, never a customer/production DSN. The suite does not silently skip connection failures.

PHP 7.4 runtime compatibility is checked with PHP lint over `src/` and `php tests/runtime.php`. Consumers can install the runtime library on PHP 7.4; developing the repository itself requires PHP 8.2+ because Composer also resolves its Pest development dependencies.

Optional Clockwork dependency installation is not needed for core tests. Disabled-listener behavior is tested; this is not a full compatibility test of the external Clockwork package. WordPress tests cover adapter boundaries, not a full WordPress end-to-end installation.
