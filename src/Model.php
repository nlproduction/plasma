<?php

namespace Plasma;

use Plasma\Adapter\DatabaseAdapter;
use Plasma\Internal\Sql;
use Plasma\Internal\WhereCompiler;

/**
 * Model - Prisma-like Table Proxy for PHP
 *
 * Provides Prisma-style CRUD methods for a single database table.
 * Works with any DatabaseAdapter (PdoAdapter, WpdbAdapter, custom adapters).
 *
 * Features:
 * - Prisma-like methods: findMany(), findUnique(), findFirst(), create(), update(), delete(), count()
 * - Type-safe queries with array-based DSL
 * - Relation loading (hasMany, hasOne, belongsTo) with N+1 prevention
 * - JSON serializable query structure (perfect for workflows)
 * - Compatible with PHP 7.4+
 *
 * Usage:
 * ```php
 * use Plasma\Model;
 * use Plasma\Adapter\PdoAdapter;
 *
 * $adapter = new PdoAdapter(['dsn' => '...', 'prefix' => 'app_']);
 * $users = new Model('users', $adapter);
 *
 * // Find all active users
 * $activeUsers = $users->findMany([
 *     'where' => ['active' => true],
 *     'orderBy' => ['created_at' => 'desc'],
 *     'take' => 10
 * ]);
 *
 * // Create user
 * $newUser = $users->create([
 *     'data' => ['name' => 'John', 'email' => 'john@example.com']
 * ]);
 *
 * // Relations
 * $posts = new Model('posts', $adapter);
 * $posts->hasMany('comments', 'comments', 'post_id');
 *
 * $postsWithComments = $posts->findMany([
 *     'include' => ['comments' => true]
 * ]);
 * ```
 */
class Model
{
  protected string $table;
  protected string $primaryKey;
  protected array $relations = [];
  protected array $fieldTypes = [];
  protected DatabaseAdapter $adapter;

  /**
   * @var callable|null fn(string $modelKey): Model
   */
  protected $modelResolver;

  public function __construct(
    string $table,
    DatabaseAdapter $adapter,
    string $primaryKey = 'id',
    array $relations = [],
    array $fieldTypes = []
  ) {
    $this->table = Sql::table($table, $adapter->getPrefix());
    $this->adapter = $adapter;
    $this->primaryKey = Sql::identifier($primaryKey);
    $this->relations = $relations;
    $this->fieldTypes = $fieldTypes;
  }

  /**
   * @return array<string, string>
   */
  public function getFieldTypes(): array
  {
    return $this->fieldTypes;
  }

  /**
   * Set resolver for schema-backed related models (used by Plasma).
   *
   * @param callable $resolver fn(string $modelKey): Model
   * @return self
   */
  public function setModelResolver(callable $resolver): self
  {
    $this->modelResolver = $resolver;
    return $this;
  }

  /**
   * Find many records (like Prisma findMany)
   *
   * @param array $options
   * @return array
   * @throws \InvalidArgumentException
   */
  public function findMany(array $options = []): array
  {
    // Prisma rule: cannot use both select and include at the same level
    if (!empty($options['select']) && !empty($options['include'])) {
      throw new \InvalidArgumentException(
        'Cannot use both "select" and "include" at the same level. Use either select (for specific fields) or include (to load relations).'
      );
    }

    if (array_key_exists('include', $options) && !is_array($options['include'])) {
      throw new \InvalidArgumentException('include must be an array.');
    }
    foreach ($options['include'] ?? [] as $name => $settings) {
      if ($settings === false) {
        continue;
      }
      if (!isset($this->relations[$name]) || ($settings !== true && !is_array($settings))) {
        throw new \InvalidArgumentException('Unknown relation or invalid include options.');
      }
      if (is_array($settings) && (array_key_exists('take', $settings) || array_key_exists('skip', $settings))) {
        throw new \InvalidArgumentException('Per-parent relation pagination is not supported.');
      }
    }
    $builder = new QueryBuilder($this->table, $this->adapter);
    $results = $builder->execute($options);

    if (!empty($options['include']) && !empty($results)) {
      $results = $this->loadRelations($results, $options['include']);
    }

    return $this->hydrateRows($results);
  }

  /**
   * Find unique record by where condition
   *
   * @param array $where
   * @return array|null
   */
  public function findUnique(array $where): ?array
  {
    $options = isset($where['where']) && is_array($where['where']) ? $where : ['where' => $where];
    $options['take'] = 1;
    $results = $this->findMany($options);

    return $results[0] ?? null;
  }

  /**
   * Find first record matching conditions
   *
   * @param array $options
   * @return array|null
   */
  public function findFirst(array $options = []): ?array
  {
    $options['take'] = 1;
    $results = $this->findMany($options);

    return $results[0] ?? null;
  }

  /**
   * Create new record
   *
   * @param array $data ['data' => [...]] or just [...]
   * @return array Created record
   * @throws \RuntimeException If insert fails
   */
  public function create(array $data): array
  {
    $insertData = $data['data'] ?? $data;
    $insertData = $this->serializeRow($insertData);

    $result = $this->adapter->insert($this->table, $insertData);

    if ($result === false) {
      throw new \RuntimeException("Failed to create record in table '{$this->table}'");
    }

    // Determine the primary key value to fetch the created record:
    // - String PK (e.g. options.key): value was provided in $insertData
    // - Auto-increment PK (e.g. id): adapter returns the inserted ID as $result
    if (isset($insertData[$this->primaryKey])) {
      $pkValue = $insertData[$this->primaryKey];
    } else {
      $pkValue = $result; // auto-increment insert_id from adapter
    }
    $record = $this->findUnique([$this->primaryKey => $pkValue]);

    if (!$record) {
      throw new \RuntimeException("Failed to retrieve created record from table '{$this->table}'");
    }

    return $record;
  }

  /**
   * Update records
   *
   * @param array $options ['where' => [...], 'data' => [...]]
   * @return int Number of rows updated
   */
  public function update(array $options): int
  {
    Sql::equalityWhere($options['where'] ?? []);
    if (empty($options['data'])) {
      return 0;
    }

    $result = $this->adapter->update(
      $this->table,
      $this->serializeRow($options['data']),
      $options['where']
    );

    if ($result === false) {
      throw new \RuntimeException('Database write failed.');
    }
    return $result;
  }

  /**
   * Update one record by primary key
   *
   * @param mixed $id
   * @param array $data
   * @return array|null Updated record
   */
  public function updateOne($id, array $data): ?array
  {
    $this->update([
      'where' => [$this->primaryKey => $id],
      'data' => $data
    ]);

    return $this->findUnique([$this->primaryKey => $id]);
  }

  /**
   * Delete records
   *
   * @param array $where ['where' => [...]] or just [...]
   * @return int Number of rows deleted
   */
  public function delete(array $where): int
  {
    $whereClause = $where['where'] ?? $where;
    Sql::equalityWhere($whereClause);

    $result = $this->adapter->delete($this->table, $whereClause);

    if ($result === false) {
      throw new \RuntimeException('Database write failed.');
    }
    return $result;
  }

  /**
   * Delete one record by primary key
   *
   * @param mixed $id
   * @return int Number of rows deleted (0 or 1)
   */
  public function deleteOne($id): int
  {
    return $this->delete([$this->primaryKey => $id]);
  }

  /**
   * Count records
   *
   * @param array $options
   * @return int
   */
  public function count(array $options = []): int
  {
    $builder = new QueryBuilder($this->table, $this->adapter);
    return $builder->count($options);
  }

  /**
   * Check if record exists
   *
   * @param array $where
   * @return bool
   */
  public function exists(array $where): bool
  {
    return $this->count(['where' => $where]) > 0;
  }

  /**
   * Define a hasMany relation
   *
   * @param string $name Relation name
   * @param string $table Related table name
   * @param string $foreignKey Foreign key in related table
   * @param string|null $localKey Local key (defaults to primary key)
   * @return self
   */
  public function hasMany(string $name, string $table, string $foreignKey, ?string $localKey = null, ?string $modelKey = null): self
  {
    $this->relations[$name] = [
      'type' => 'hasMany',
      'table' => $table,
      'foreignKey' => $foreignKey,
      'localKey' => $localKey ?? $this->primaryKey,
      'modelKey' => $modelKey,
    ];
    return $this;
  }

  /**
   * Define a hasOne relation
   *
   * @param string $name Relation name
   * @param string $table Related table name
   * @param string $foreignKey Foreign key in related table
   * @param string|null $localKey Local key (defaults to primary key)
   * @return self
   */
  public function hasOne(string $name, string $table, string $foreignKey, ?string $localKey = null, ?string $modelKey = null): self
  {
    $this->relations[$name] = [
      'type' => 'hasOne',
      'table' => $table,
      'foreignKey' => $foreignKey,
      'localKey' => $localKey ?? $this->primaryKey,
      'modelKey' => $modelKey,
    ];
    return $this;
  }

  /**
   * Define a belongsTo relation
   *
   * @param string $name Relation name
   * @param string $table Related table name
   * @param string $foreignKey Foreign key in current table
   * @param string|null $localKey Local key in related table (defaults to 'id')
   * @return self
   */
  public function belongsTo(string $name, string $table, string $foreignKey, ?string $localKey = null, ?string $modelKey = null): self
  {
    $this->relations[$name] = [
      'type' => 'belongsTo',
      'table' => $table,
      'foreignKey' => $foreignKey,
      'localKey' => $localKey ?? 'id',
      'modelKey' => $modelKey,
    ];
    return $this;
  }

  /**
   * Load relations for results (N+1 prevention via batch loading)
   *
   * @param array $results
   * @param array $include ['relationName' => true] or ['relationName' => ['where' => [...]]]
   * @return array
   */
  protected function loadRelations(array $results, array $include): array
  {
    foreach ($include as $relationName => $relationOptions) {
      if ($relationOptions === false) {
        continue;
      }
      $relation = $this->relations[$relationName];
      $many = $relation['type'] === 'hasMany';
      $belongsTo = $relation['type'] === 'belongsTo';
      $sourceKey = $belongsTo ? $relation['foreignKey'] : $relation['localKey'];
      $targetKey = $belongsTo ? $relation['localKey'] : $relation['foreignKey'];
      $ids = array_values(array_unique(array_filter(array_column($results, $sourceKey), function ($id) {
        return $id !== null;
      })));
      $relatedOptions = is_array($relationOptions) ? $relationOptions : [];
      // Never replace an explicit constraint on the join key with the batch IDs.
      $joinFilter = [$targetKey => ['in' => $ids]];
      $relatedOptions['where'] = empty($relatedOptions['where']) ? $joinFilter :
        ['AND' => [$relatedOptions['where'], $joinFilter]];
      $removeJoinKey = false;
      if (!empty($relatedOptions['select'])) {
        $select = $relatedOptions['select'];
        (new QueryBuilder($relation['table'], $this->adapter))->buildSelect($select);
        if (WhereCompiler::isList($select)) {
          $removeJoinKey = !in_array($targetKey, $select, true);
          if ($removeJoinKey) {
            $relatedOptions['select'][] = $targetKey;
          }
        } else {
          $removeJoinKey = empty($select[$targetKey]);
          $relatedOptions['select'][$targetKey] = true;
        }
      }
      $relatedData = $ids === [] ? [] : $this->resolveRelatedModel($relation, $relation['table'])->findMany($relatedOptions);
      $grouped = [];
      foreach ($relatedData as $item) {
        $key = (string) $item[$targetKey];
        if ($removeJoinKey) {
          unset($item[$targetKey]);
        }
        if ($many) {
          $grouped[$key][] = $item;
        } elseif (!array_key_exists($key, $grouped)) {
          // hasOne/belongsTo keep the first row in the requested order.
          $grouped[$key] = $item;
        }
      }
      foreach ($results as $index => $result) {
        $key = isset($result[$sourceKey]) ? (string) $result[$sourceKey] : null;
        $results[$index][$relationName] = $key !== null && array_key_exists($key, $grouped) ? $grouped[$key] : ($many ? [] : null);
      }
    }
    return $results;
  }

  /**
   * @param array<string, mixed> $relation
   * @param string $relatedTable
   * @return Model
   */
  protected function resolveRelatedModel(array $relation, string $relatedTable): Model
  {
    $modelKey = isset($relation['modelKey']) ? (string) $relation['modelKey'] : '';
    if ($modelKey !== '' && $this->modelResolver !== null) {
      return ($this->modelResolver)($modelKey);
    }

    return new Model($relatedTable, $this->adapter);
  }

  /**
   * @param array<int, array<string, mixed>> $rows
   * @return array<int, array<string, mixed>>
   */
  protected function hydrateRows(array $rows): array
  {
    if ($this->fieldTypes === []) {
      return $rows;
    }

    foreach ($rows as $i => $row) {
      $rows[$i] = SchemaFieldCaster::hydrateRecord($row, $this->fieldTypes);
    }

    return $rows;
  }

  /**
   * @param array<string, mixed> $data
   * @return array<string, mixed>
   */
  protected function serializeRow(array $data): array
  {
    if ($this->fieldTypes === []) {
      return $data;
    }

    return SchemaFieldCaster::serializeRecord($data, $this->fieldTypes);
  }
}
