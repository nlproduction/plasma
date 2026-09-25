# Query reference

All examples use a `Plasma\Model`, such as `$products = $db->table('products')`.

## Reads

```php
$rows = $products->findMany([
    'where' => [
        'active' => true,
        'OR' => [['price' => ['lt' => 50]], ['name' => ['contains' => 'coffee']]],
    ],
    'select' => ['id', 'name'], // or ['id' => true, 'name' => true]
    'orderBy' => [['name' => 'asc'], ['id' => 'desc']],
    'take' => 20,
    'skip' => 0,
]);
```

`findFirst($options)` returns a row or null. `findUnique(['where' => ['id' => 7]])` accepts the same read options and limits to one row. The earlier `findUnique(['id' => 7])` form remains supported. It does **not** prove that a filter is unique; use database unique constraints and a primary/unique-key filter.

`count(['where' => ...])` counts all matching rows, independently of pagination. `exists($where)` returns a boolean. Both count and reads use the same predicate compiler.

For schema-backed models, fields used by `where`, `select`, `orderBy`, and writes must exist in the model schema; unknown fields are rejected before SQL compilation. Dynamic models still validate SQL identifier syntax but have no metadata allowlist.

`take` and `skip` require non-negative PHP integers. `take: 0` returns no rows. Offset-only queries work without an explicit take. An empty select means all fields; a nonempty selection enabling no fields is rejected. Sorting accepts `asc` or `desc` only. Unsupported query options are rejected rather than ignored.

## Distinct values

```php
$categories = $products->distinct(
    ['category'],
    ['where' => ['active' => true], 'orderBy' => ['category' => 'asc']]
);

$tuples = $products->distinct(
    ['category', 'active'],
    ['orderBy' => [['category' => 'asc'], ['active' => 'asc']]]
);
```

A single selected field returns a scalar list. Multiple fields return associative rows keyed by field name. Schema casting is applied to the result, so booleans, integers, JSON, and other declared types are hydrated consistently with `findMany()`.

`distinct()` accepts `where`, `orderBy`, `take`, and `skip`. It rejects `select` and `include`; selected fields must be unique, declared by schema-backed models, and are the only fields allowed in `orderBy`. This restriction keeps the generated `SELECT DISTINCT` portable across the supported MySQL/MariaDB and SQLite dialects.

## Supported scalar filters

- Equality: `['name' => 'Coffee']` or `['name' => ['equals' => 'Coffee']]`.
- Comparisons: `gt`, `gte`, `lt`, `lte`.
- Membership: `in`, `notIn` with a list of non-null finite scalar values.
- Text: `contains`, `startsWith`, `endsWith`. `%`, `_`, and backslashes in values are treated literally; collation controls case sensitivity.
- Negation: `not` accepts a scalar, null, or another field-operator object. Repeated field-level negation is supported.
- Null: direct null or `equals: null` emits `IS NULL`; `not: null` emits `IS NOT NULL`.

Text/comparison filters do not accept null. Null does not automatically match a negated non-null predicate: ordinary SQL three-valued logic applies. Use an explicit `OR` with `field: null` when needed.

## Boolean groups

`AND`, `OR`, and `NOT` may be nested; fields at the same object level are implicitly ANDed. A list under `NOT` means `NOT child1 AND NOT child2`, not `NOT (child1 AND child2)`.

An empty root filter, `AND: []`, and `NOT: []` match all rows. `OR: []` and `in: []` match no rows; `notIn: []` matches all. An empty child predicate is true; for example `OR: [{}]` matches all rows. An empty field-operator object is invalid, except `not: {}`, which is a no-op. These explicitly defined edge cases are not a promise of exhaustive Prisma parity.

Default safety limits are 64 recursive compiler levels, 1,000 visited predicate/operator nodes, and 1,000 values per membership list. A directly constructed `QueryBuilder` can override them with its third argument (`maxDepth`, `maxConditions`, `maxListValues`). API applications should additionally cap request size and page size.

## Writes

```php
$row = $products->create(['data' => ['name' => 'Coffee']]);
$affected = $products->update(['where' => ['id' => $row['id']], 'data' => ['name' => 'Tea']]);
$affected = $products->delete(['where' => ['id' => $row['id']]]);
```

`create()` returns the inserted row. `update()` and `delete()` return affected-row counts, not a Prisma-style record. The `create($data)` and `delete($where)` shorthand forms remain available. `updateOne($id, $data)` returns the resulting row or null; `deleteOne($id)` returns the affected count.

### Bulk insert and upsert

```php
$processed = $products->createMany([
    'data' => [
        ['sku' => 'A-1', 'name' => 'Coffee'],
        ['name' => 'Tea', 'sku' => 'B-2'], // key order may differ
    ],
]);

$processed = $products->upsertMany([
    'data' => [
        ['sku' => 'A-1', 'name' => 'Coffee beans'],
        ['sku' => 'C-3', 'name' => 'Cocoa'],
    ],
    'conflictFields' => ['sku'],
    'updateFields' => ['name'],
]);
```

Both methods also accept the row list directly as shorthand. Rows must be nonempty associative arrays with the same column set; schema allowlists and serialization apply before SQL execution. Batches are bounded to 1,000 rows and execute as one statement without per-row readback. They return the number of input rows processed after successful execution, not the connection's affected-row count. Empty batches return `0`.

`upsertMany()` defaults `conflictFields` to the model primary key and `updateFields` to all other supplied columns. Conflict and update fields must be present in every row, valid for the model, unique within their lists, and non-overlapping. MySQL/MariaDB chooses the conflicting unique key according to database constraints (`ON DUPLICATE KEY UPDATE`); SQLite uses the explicit `conflictFields` target (`ON CONFLICT`). Unsupported adapter dialects fail explicitly.

**Ordinary `update()` and `delete()` use equality-only filters** (`field => scalar/null`). Recursive read filters do not apply to update/delete. Missing/empty filters and nested mutation operators are rejected. Failed database operations throw. Zero affected rows is a successful no-op, not an error.

## Scope

This is a documented Prisma-inspired subset, not Prisma Client. No relation filters (`some`, `every`, `none`), JSON-path filters, `mode`, cursor pagination, nested writes, composite primary keys, aggregates beyond `distinct`, joins, or migrations. Use application-owned SQL where the model API is insufficient.

Schema metadata validates model fields; it does not authenticate a caller or authorize access to a model/row. See [the security boundary](../SECURITY.md).
