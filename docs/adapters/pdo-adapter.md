# PDO adapter

```php
$adapter = new \Plasma\Adapter\PdoAdapter([
    'dsn' => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'username' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'prefix' => 'app_',
    'options' => [PDO::ATTR_TIMEOUT => 5],
]);
```

MySQL/MariaDB and SQLite are the supported SQL dialects. Install the corresponding PDO driver. Exception mode is enforced; database failures must not look like empty successful results. `query()` returns associative rows, `getVar()` returns the first value or null when no row exists, and `execute()` runs a non-row statement through `PDO::exec()` and returns its affected-row count where meaningful. `getDialect()` returns the active PDO driver name.

## Parameter handling

`DatabaseAdapter::prepare($sql, ...$values)` is a **WordPress-style SQL formatter**, not `PDO::prepare()`. It recognizes `%s`, `%d`, `%f`, and `%%`; it validates placeholder counts and quotes values in one pass using the active connection. Values containing `%s`, backslashes, or `$1` are not reparsed.

Read filters use this formatter. Insert/update/delete use native PDO prepared statements and typed bound values. Bulk model writes validate identifiers and rows, format values once through the active adapter, and then call `execute()`. Raw SQL passed to `query()` or `execute()` is trusted application code. For custom prepared queries, use `getPdo()->prepare()` yourself. Never concatenate untrusted SQL or identifiers.

```php
$adapter->execute('CREATE TABLE app_jobs (id INT PRIMARY KEY)');
```

Do not use `query()` merely to run DDL/DML: its contract is row retrieval.

## Table names and writes

Logical names and already-prefixed names both resolve once. Write columns are validated; update/delete require nonempty scalar equality filters. Null equality compiles as `IS NULL`. PDO failures raise exceptions.

## Transactions

```php
$db->transaction(function (\Plasma\Plasma $db) {
    $db->table('products')->create(['name' => 'Coffee']);
});
```

Nested transactions use savepoints. Callback exceptions and PHP Errors trigger rollback. Transaction depth changes only after a successful SQL command. Do not mix raw connection transaction commands with Plasma's accounting, and do not assume MySQL DDL is transactional. Use transactional tables (for example InnoDB).
