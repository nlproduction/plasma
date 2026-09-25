# Adapters

`DatabaseAdapter` separates model/query behavior from database access. Bundled adapters are [PDO](pdo-adapter.md), [WordPress wpdb](wpdb-adapter.md), and the optional [event decorator](event-adapter.md).

The interface includes:

- row-returning reads through `query()` and scalar reads through `getVar()`;
- non-row DDL/DML through `execute()`, which returns an affected-row count where the database provides one;
- equality-only single-row/table writes through `insert()`, `update()`, and `delete()`;
- SQL formatting through `prepare()` and `escapeLike()`;
- table prefixes and nested transaction methods.

`query()` and `execute()` intentionally have different contracts. Use `query()` when rows are expected. Use `execute()` for statements such as `CREATE TABLE`, `ALTER TABLE`, bulk `INSERT`, or raw `UPDATE`; database failures must throw rather than appear as empty rows. Insert returns the generated integer identifier when available; explicitly supplied primary keys are read from inserted data by `Model`.

## Optional capabilities

`DialectAwareAdapter` exposes a normalized SQL dialect name such as `mysql`, `mariadb`, or `sqlite`. Generic reads do not need it, but dialect-specific operations such as `upsertMany()` require it and fail explicitly when unavailable.

`SchemaProvidingAdapter` supplies bundled/default model metadata. Transparent decorators should forward this capability so wrapping `WpdbAdapter` does not hide the WordPress core schema. `EventDatabaseAdapter` forwards both schema-provider and dialect behavior.

Custom adapters must provide connection-appropriate SQL escaping, associative result rows, accurate transaction depth, a real non-row `execute()` implementation, and failures that are not silently treated as success. Implement `DialectAwareAdapter` when dialect-specific model operations should be available. The bundled compiler emits MySQL/SQLite-compatible identifier syntax; changing connections does not make it a universal SQL-dialect compiler.
