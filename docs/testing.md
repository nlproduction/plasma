# Testing

Use PHP 8.1+ for Pest and install `pdo_sqlite`. The default suite executes real SQL against isolated in-memory databases; WordPress boundary tests use a mock wpdb. No external database credentials are required.

```bash
composer install
composer test
composer validate --strict
php examples/sqlite.php
```

An optional external database run uses `PLASMA_TEST_DSN`, `PLASMA_TEST_USER`, and `PLASMA_TEST_PASSWORD`. The database integration fixture creates and drops randomly prefixed tables. **Use only a disposable database**, never a customer/production DSN. The suite does not silently skip connection failures.

PHP 7.4 runtime compatibility can be checked with `composer install --no-dev`, PHP lint over `src/`, and `php examples/sqlite.php`; the Pest toolchain itself requires a newer PHP runtime.

Optional Clockwork dependency installation is not needed for core tests. Disabled-listener behavior is tested; this is not a full compatibility test of the external Clockwork package. WordPress tests cover adapter boundaries, not a full WordPress end-to-end installation.
