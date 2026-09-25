# Adapters

`DatabaseAdapter` separates model/query behavior from database access. Bundled adapters are [PDO](pdo-adapter.md), [WordPress wpdb](wpdb-adapter.md), and the optional [event decorator](event-adapter.md).

The interface includes reads (`query`, `getVar`), equality-only writes (`insert`, `update`, `delete`), SQL formatting (`prepare`, `escapeLike`), table prefixes, and nested transaction methods. Insert returns the generated integer identifier when available; explicitly supplied primary keys are read from the inserted data by Model.

Custom adapters must provide connection-appropriate SQL escaping, associative result rows, accurate transaction depth, and failures that are not silently treated as success. The bundled compiler emits MySQL/SQLite-compatible identifier syntax; changing connections does not make it a universal SQL-dialect compiler.
