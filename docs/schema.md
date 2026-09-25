# Schemas and relationships

A Plasma schema is an optional dictionary of model names. It describes data, not forms, UI controls, permissions, or migrations.

```php
$schema = [
    'author' => [
        'table' => 'authors',
        'primaryKey' => 'ID',
        'fields' => ['ID' => ['type' => 'int'], 'name' => ['type' => 'string']],
        'relations' => [
            'books' => ['type' => 'hasMany', 'model' => 'book', 'foreignKey' => 'author_id'],
        ],
    ],
    'book' => [
        'table' => 'books',
        'primaryKey' => 'id',
        'fields' => [
            'id' => ['type' => 'int'],
            'author_id' => ['type' => 'int'],
            'published' => ['type' => 'boolean'],
            'metadata' => ['type' => 'json'],
        ],
        'relations' => [
            'author' => ['type' => 'belongsTo', 'model' => 'author', 'foreignKey' => 'author_id'],
        ],
    ],
];
$db->registerSchema($schema);
```

Alternatively, `new Plasma($adapter, [$schemaArray, $jsonFile])` loads sources in order. `registerSchema()` validates the full batch before merging. Later definitions replace whole models. Missing files, invalid JSON, and malformed definitions raise exceptions; they are not silently ignored. Existing JSON exports using `__PREFIX__` in table names remain supported.

`getSchema()` returns the model dictionary. Reacquire model handles after registering replacements: cached handles are invalidated, but previously returned objects are not mutated.

## Tables and primary keys

`table` is required. `primaryKey` defaults to `id`; composite keys are not supported. Logical names and already-prefixed names are normalized once. Use a separate client/adapter for each table-prefix namespace. Never let clients select arbitrary physical tables.

Without schemas, `table('products', 'id')` and `$db->products` still work. Schemas are not table allowlists: undeclared dynamic tables remain accessible to trusted PHP code.

## Casting

| Type | Read/write behavior |
| --- | --- |
| `int` | PHP integer |
| `bigint` | PHP integer when representable; otherwise a decimal integer string |
| `float` | Read as float; avoid for exact-money arithmetic |
| `boolean` | Reads booleans; writes 0/1 (`false` string is not truthy) |
| `json` | Reads decoded arrays/scalars; encodes arrays/objects on write; invalid JSON throws |
| `datetime` | Strings on read; `DateTimeInterface` writes as `Y-m-d H:i:s` |
| `string` | No automatic coercion |

Null remains null. Preserve exact decimals as strings. There is no generated PHP class, compile-time type safety, input validation derived from nullability/defaults, or automatic migration. Schema fields provide casting metadata, not application authorization.

## Relationships

```php
$books = $db->book->findMany(['include' => ['author' => true]]);
$authors = $db->author->findMany([
    'include' => ['books' => ['where' => ['published' => true], 'orderBy' => ['id' => 'desc']]],
]);
```

`hasMany` and `hasOne` use the current model's primary key as the default `localKey`; `foreignKey` lives in the related table. `belongsTo` uses `foreignKey` on the current row and the related model's primary key as `localKey`. Set `localKey` explicitly for another key.

One additional query loads a relation for the returned batch, rather than one per parent. Caller filters are combined with the join constraint, not overwritten. Missing has-many results are `[]`; missing singular results are `null`. `include => false` skips a relation. Unknown enabled relations are rejected.

A related `select` may omit the join key; Plasma retrieves it internally and removes it from the requested projection. Root `select` and `include` cannot be combined. Per-parent relation `take`/`skip` is rejected, rather than incorrectly applying a single limit to the whole batch. Nested includes are supported; relationship-filter predicates such as `some`/`every` are not.

Manual definitions are also available on a model: `hasMany($name, $table, $foreignKey, $localKey)`, `hasOne(...)`, and `belongsTo(...)`.
