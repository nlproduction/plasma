<?php

namespace Plasma;

use Plasma\Adapter\DatabaseAdapter;
use Plasma\Internal\Sql;

/**
 * Plasma - Schema-driven Database Client
 *
 * Acts as the main entry point for database operations.
 * Loads a schema.json file and lazily creates Model instances on demand.
 *
 * Features:
 * - Schema-driven: reads models, tables, primaryKeys and relations from JSON
 * - Lazy-loading: Model instances are created only on first access
 * - Dynamic tables: any table not in schema is accessible via magic __get()
 * - Adapter-agnostic: works with any DatabaseAdapter
 * - Transaction support
 *
 * Usage:
 * ```php
 * use Plasma\Plasma;
 * use Plasma\Adapter\PdoAdapter;
 *
 * $adapter = new PdoAdapter(['dsn' => 'mysql:host=localhost;dbname=mydb', 'prefix' => 'app_']);
 *
 * // Single schema file
 * $db = new Plasma($adapter, ['/path/to/schema.json']);
 *
 * // Multiple schema files (merged, later files override earlier ones on conflict)
 * $db = new Plasma($adapter, ['/path/to/core-schema.json', '/path/to/plugin-schema.json']);
 *
 * // Access model defined in schema (lazy-loaded on first use)
 * $locations = $db->location->findMany(['where' => ['active' => true]]);
 *
 * // Access dynamic table not in schema
 * $rows = $db->some_custom_table->findMany([]);
 *
 * // Transaction
 * $db->transaction(function (Plasma $db) {
 *     $db->location->create(['data' => ['name' => 'HQ']]);
 *     $db->region->create(['data' => ['location_id' => 1, 'name' => 'North']]);
 * });
 * ```
 */
class Plasma
{
  private DatabaseAdapter $adapter;
  private string $prefix;

  /**
   * Raw schema data loaded from schema.json
   *
   * @var array<string, array>
   */
  private array $schema = [];

  /**
   * Lazy-loaded Model instances keyed by model name
   *
   * @var array<string, Model>
   */
  private array $models = [];

  /**
   * @param DatabaseAdapter $adapter
   * @param array $schemaPaths List of JSON file paths and/or PHP schema arrays (later models override earlier)
   */
  public function __construct(DatabaseAdapter $adapter, array $schemaPaths = [])
  {
    $this->adapter = $adapter;
    $this->prefix = $adapter->getPrefix();

    foreach ($schemaPaths as $source) {
      if (is_array($source)) {
        $this->registerSchema($source);
        continue;
      }
      if (!is_string($source) || $source === '' || !is_file($source) || !is_readable($source)) {
        throw new \InvalidArgumentException('Schema source must be a readable JSON file or a schema array.');
      }
      $json = file_get_contents($source);
      if ($json === false) {
        throw new \RuntimeException('Cannot read schema file.');
      }
      $schema = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
      if (!is_array($schema)) {
        throw new \InvalidArgumentException('Schema JSON must contain a model dictionary.');
      }
      $this->registerSchema($schema);
    }
  }

  /** Register model metadata directly from PHP. Later definitions replace whole models. */
  public function registerSchema(array $schema): self
  {
    foreach ($schema as $name => $definition) {
      if (!is_string($name) || !is_array($definition)) {
        throw new \InvalidArgumentException('Schema must map model names to definitions.');
      }
      Sql::identifier($name);
      if (!isset($definition['table']) || !is_string($definition['table'])) {
        throw new \InvalidArgumentException('Every model needs a table name.');
      }
      Sql::table($definition['table'], $this->prefix);
      $pk = $definition['primaryKey'] ?? 'id';
      if (!is_string($pk)) {
        throw new \InvalidArgumentException('Composite primary keys are not supported.');
      }
      Sql::identifier($pk);
      $fields = $definition['fields'] ?? [];
      $relations = $definition['relations'] ?? [];
      if (!is_array($fields) || !is_array($relations)) {
        throw new \InvalidArgumentException('fields and relations must be dictionaries.');
      }
      foreach ($fields as $field => $config) {
        if (!is_string($field) || !is_array($config) || !isset($config['type']) || !is_string($config['type'])) {
          throw new \InvalidArgumentException('Each field needs a name and string type.');
        }
        Sql::identifier($field);
      }
      foreach ($relations as $relation => $config) {
        if (!is_string($relation) || !is_array($config) || !isset($config['model'], $config['type']) ||
            !is_string($config['model']) || !in_array($config['type'], ['hasMany', 'hasOne', 'belongsTo'], true)) {
          throw new \InvalidArgumentException('Invalid relation definition.');
        }
        Sql::identifier($relation);
        Sql::identifier($config['model']);
        foreach (['foreignKey', 'localKey'] as $key) {
          if (isset($config[$key])) {
            if (!is_string($config[$key])) {
              throw new \InvalidArgumentException('Relation keys must be strings.');
            }
            Sql::identifier($config[$key]);
          }
        }
      }
    }
    // Validate the entire batch before changing the registry.
    $this->schema = array_replace($this->schema, $schema);
    $this->models = [];
    return $this;
  }

  /** Return data metadata, not UI settings or an authorization policy. */
  public function getSchema(): array
  {
    return $this->schema;
  }

  /**
   * Lazy-load a Model by schema key or fall back to raw table proxy
   *
   * @param string $name Schema model key (e.g. 'location', 'workflow') or raw table name
   * @return Model
   */
  public function __get(string $name): Model
  {
    if (!isset($this->models[$name])) {
      if (isset($this->schema[$name])) {
        $this->models[$name] = $this->buildFromSchema($name);
      } else {
        // Dynamic table not defined in schema
        $this->models[$name] = new Model($this->getTableName($name), $this->adapter);
      }
    }

    return $this->models[$name];
  }

  /**
   * Explicitly get a table proxy by raw table name (without prefix)
   *
   * @param string $name Table name without prefix
   * @param string $primaryKey Primary key column
   * @return Model
   */
  public function table(string $name, string $primaryKey = 'id'): Model
  {
    return new Model($this->getTableName($name), $this->adapter, $primaryKey);
  }

  /**
   * Get a Model by schema key (same as magic property access).
   *
   * @param string $name Schema model key (e.g. location, region)
   * @return Model
   */
  public function model(string $name): Model
  {
    return $this->__get($name);
  }

  /**
   * Resolve schema model key from model key or table name.
   *
   * Accepts schema keys (location), logical names (location), or prefixed tables (app_location).
   *
   * @param string $tableOrModel Model key or table identifier
   * @return string|null Schema model key when found in schema, null otherwise
   */
  public function resolveModelKey(string $tableOrModel): ?string
  {
    $input = strtolower(trim($tableOrModel));
    if ($input === '') {
      return null;
    }
    foreach ($this->schema as $key => $definition) {
      $full = $this->logicalTableName($definition['table']);
      $logical = $this->prefix !== '' && strpos($full, $this->prefix) === 0
        ? substr($full, strlen($this->prefix)) : $full;
      if (in_array($input, array_map('strtolower', [$key, $full, $logical, $definition['table']]), true)) {
        return $key;
      }
    }
    return null;
  }

  /**
   * Resolve Model for DataSource db config (model key preferred, table name as fallback).
   *
   * @param array $config DataSource config with "model" and/or "table"
   * @return Model Schema-backed model when resolvable, otherwise dynamic table proxy
   */
  public function resolveModelFromDataSourceConfig(array $config): Model
  {
    $modelKey = isset($config['model']) ? trim((string) $config['model']) : '';
    if ($modelKey !== '') {
      return $this->model($modelKey);
    }

    $tableName = isset($config['table']) ? trim((string) $config['table']) : '';
    if ($tableName === '') {
      throw new \InvalidArgumentException(
        'DataSource config must define "model" or "table" for type "db".'
      );
    }

    $resolvedKey = $this->resolveModelKey($tableName);
    if ($resolvedKey !== null) {
      return $this->model($resolvedKey);
    }

    return $this->__get($tableName);
  }

  /**
   * Get full table name with prefix
   *
   * @param string $name Table name without prefix
   * @return string
   */
  public function getTableName(string $name): string
  {
    return Sql::table($name, $this->prefix);
  }

  /**
   * Get the underlying database adapter
   *
   * @return DatabaseAdapter
   */
  public function getAdapter(): DatabaseAdapter
  {
    return $this->adapter;
  }

  /**
   * Execute raw SQL query
   *
   * @param string $sql
   * @return array|null
   */
  public function raw(string $sql): ?array
  {
    $results = $this->adapter->query($sql);
    return !empty($results) ? $results : null;
  }

  /**
   * Start a database transaction
   */
  public function beginTransaction(): void
  {
    $this->adapter->beginTransaction();
  }

  /**
   * Commit the current transaction
   */
  public function commit(): void
  {
    $this->adapter->commit();
  }

  /**
   * Rollback the current transaction
   */
  public function rollback(): void
  {
    $this->adapter->rollback();
  }

  /**
   * Execute a callback inside a transaction
   *
   * @param callable $callback Receives Plasma instance as argument
   * @return mixed Result returned from callback
   * @throws \Throwable Re-throws the original error after rollback
   */
  public function transaction(callable $callback)
  {
    $this->beginTransaction();

    try {
      $result = $callback($this);
      $this->commit();
      return $result;
    } catch (\Throwable $e) {
      $this->rollback();
      throw $e;
    }
  }

  /**
   * Drop all tables from loaded schema plus optional extra logical table names.
   *
   * Disables foreign key checks for MySQL during drops. Clears lazy-loaded models.
   *
   * @param string[] $extraTables Logical names without adapter prefix (e.g. options, migrations)
   * @return void
   */
  public function reset(array $extraTables = ['options', 'migrations']): void
  {
    $tables = [];

    foreach ($this->schema as $def) {
      if (isset($def['table']) && is_string($def['table'])) {
        $tables[] = $this->logicalTableName($def['table']);
      }
    }

    foreach ($extraTables as $name) {
      if (is_string($name) && $name !== '') {
        $tables[] = $this->getTableName($name);
      }
    }

    $tables = array_values(array_unique($tables));

    $this->adapter->query('SET FOREIGN_KEY_CHECKS=0');

    try {
      foreach ($tables as $logical) {
        $escaped = str_replace('`', '``', $logical);
        $this->adapter->query("DROP TABLE IF EXISTS `{$escaped}`");
      }
    } finally {
      $this->adapter->query('SET FOREIGN_KEY_CHECKS=1');
    }

    $this->models = [];
  }

  /**
   * Table name for Model (logical or prefixed names are normalized exactly once).
   */
  private function logicalTableName(string $tableDef): string
  {
    return $this->getTableName($tableDef);
  }

  private function buildFromSchema(string $name): Model
  {
    $def = $this->schema[$name];
    $table = $this->logicalTableName($def['table']);
    $primaryKey = $def['primaryKey'] ?? 'id';

    $fieldTypes = SchemaFieldCaster::fieldTypesFromSchema($def['fields'] ?? []);
    $model = new Model($table, $this->adapter, $primaryKey, [], $fieldTypes);

    $plasma = $this;
    $model->setModelResolver(function ($key) use ($plasma) {
      return $plasma->model($key);
    });

    foreach ($def['relations'] ?? [] as $relName => $rel) {
      $relModelKey = $this->resolveModelKey($rel['model']) ?? $rel['model'];

      $relTableRaw = isset($this->schema[$relModelKey])
        ? $this->schema[$relModelKey]['table']
        : '__PREFIX__' . $relModelKey;

      $relTable = $this->logicalTableName($relTableRaw);

      switch ($rel['type']) {
        case 'hasMany':
          $foreignKey = $rel['foreignKey'] ?? $name . '_id';
          $model->hasMany($relName, $relTable, $foreignKey, $rel['localKey'] ?? $primaryKey, $relModelKey);
          break;

        case 'hasOne':
          $foreignKey = $rel['foreignKey'] ?? $name . '_id';
          $model->hasOne($relName, $relTable, $foreignKey, $rel['localKey'] ?? $primaryKey, $relModelKey);
          break;

        case 'belongsTo':
          $foreignKey = $rel['foreignKey'] ?? $relModelKey . '_id';
          $model->belongsTo($relName, $relTable, $foreignKey, $rel['localKey'] ?? ($this->schema[$relModelKey]['primaryKey'] ?? 'id'), $relModelKey);
          break;
      }
    }

    return $model;
  }
}
