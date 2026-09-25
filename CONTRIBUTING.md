# Contributing

Open focused issues and pull requests with a reproducible query and expected behavior. Keep changes independent of any application framework.

Use PHP 8.1+ for the Pest development suite and install the PDO SQLite driver:

```bash
composer install
composer test
composer validate --strict
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

Runtime code targets PHP 7.4+. Do not introduce property promotion, union types, `mixed`, named arguments, match expressions, or readonly syntax in `src/`. Add regression tests that execute SQL, not just assertions that a query contains a keyword.

Model metadata describes data only. UI configuration and authorization belong to consumers. Do not weaken validation to make a failing test pass, or claim unsupported Prisma features in documentation.

Use synthetic fixtures and a disposable database. Never commit secrets. Contributions are provided under the repository's MIT license.
