<?php

namespace Plasma\Internal;

use Plasma\Adapter\DatabaseAdapter;
use Plasma\Adapter\DialectAwareAdapter;

/** @internal Compiles bounded homogeneous insert/upsert batches. */
final class BulkWriter
{
    public const MAX_BATCH_ROWS = 1000;

    private string $table;
    private DatabaseAdapter $adapter;

    /** @var array<string, true>|null */
    private $allowedFields;

    public function __construct(string $table, DatabaseAdapter $adapter, ?array $allowedFields = null)
    {
        Sql::table($table, $adapter->getPrefix());
        $this->table = $table;
        $this->adapter = $adapter;

        if ($allowedFields !== null) {
            foreach ($allowedFields as $field) {
                if (!is_string($field)) {
                    throw new \InvalidArgumentException('Allowed fields must be strings.');
                }
                Sql::identifier($field);
            }
        }
        $this->allowedFields = $allowedFields === null
            ? null
            : array_fill_keys($allowedFields, true);
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function createMany(array $rows): int
    {
        [$columns, $rows] = $this->normalizeRows($rows);
        if ($rows === []) {
            return 0;
        }

        [$valuesSql, $params] = $this->valuesSql($columns, $rows);
        $sql = 'INSERT INTO ' . $this->quotedTable()
            . ' (' . $this->quotedFields($columns) . ') VALUES ' . $valuesSql;

        $this->executePrepared($sql, $params);
        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $conflictFields
     * @param string[] $updateFields
     */
    public function upsertMany(array $rows, array $conflictFields, array $updateFields): int
    {
        [$columns, $rows] = $this->normalizeRows($rows);
        $conflictFields = $this->validateFieldList($conflictFields, 'conflictFields', false);
        $updateFields = $this->validateFieldList($updateFields, 'updateFields', true);
        if (array_intersect($conflictFields, $updateFields) !== []) {
            throw new \InvalidArgumentException('Conflict fields cannot also be update fields.');
        }
        if ($rows === []) {
            return 0;
        }

        $columnSet = array_fill_keys($columns, true);
        foreach (array_merge($conflictFields, $updateFields) as $field) {
            if (!isset($columnSet[$field])) {
                throw new \InvalidArgumentException('Bulk upsert fields must exist in every data row.');
            }
        }

        if (!$this->adapter instanceof DialectAwareAdapter) {
            throw new \RuntimeException('Bulk upsert requires an adapter that exposes its SQL dialect.');
        }

        [$valuesSql, $params] = $this->valuesSql($columns, $rows);
        $sql = 'INSERT INTO ' . $this->quotedTable()
            . ' (' . $this->quotedFields($columns) . ') VALUES ' . $valuesSql;
        $dialect = strtolower($this->adapter->getDialect());

        if ($dialect === 'mysql' || $dialect === 'mariadb') {
            $assignments = [];
            foreach ($updateFields as $field) {
                $quoted = Sql::quote($field);
                $assignments[] = $quoted . ' = VALUES(' . $quoted . ')';
            }
            if ($assignments === []) {
                $quoted = Sql::quote($conflictFields[0]);
                $assignments[] = $quoted . ' = VALUES(' . $quoted . ')';
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
        } elseif ($dialect === 'sqlite') {
            $sql .= ' ON CONFLICT (' . $this->quotedFields($conflictFields) . ') ';
            if ($updateFields === []) {
                $sql .= 'DO NOTHING';
            } else {
                $assignments = [];
                foreach ($updateFields as $field) {
                    $quoted = Sql::quote($field);
                    $assignments[] = $quoted . ' = excluded.' . $quoted;
                }
                $sql .= 'DO UPDATE SET ' . implode(', ', $assignments);
            }
        } else {
            throw new \RuntimeException('Bulk upsert is not supported for SQL dialect: ' . $dialect);
        }

        $this->executePrepared($sql, $params);
        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: string[], 1: array<int, array<string, mixed>>}
     */
    private function normalizeRows(array $rows): array
    {
        if (!self::isList($rows)) {
            throw new \InvalidArgumentException('Bulk data must be a list of row objects.');
        }
        if (count($rows) > self::MAX_BATCH_ROWS) {
            throw new \InvalidArgumentException('Bulk data exceeds the maximum batch size.');
        }
        if ($rows === []) {
            return [[], []];
        }
        if (!is_array($rows[0]) || $rows[0] === []) {
            throw new \InvalidArgumentException('Every bulk row must be a non-empty array.');
        }

        $columns = array_keys($rows[0]);
        $expected = $columns;
        sort($expected);
        foreach ($columns as $field) {
            $this->assertField($field);
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row) || $row === []) {
                throw new \InvalidArgumentException('Every bulk row must be a non-empty array.');
            }
            $actual = array_keys($row);
            sort($actual);
            if ($actual !== $expected) {
                throw new \InvalidArgumentException('Every bulk row must contain the same columns.');
            }

            $ordered = [];
            foreach ($columns as $column) {
                $ordered[$column] = $row[$column];
            }
            Sql::row($ordered);
            $normalized[] = $ordered;
        }

        return [$columns, $normalized];
    }

    /**
     * @param string[] $columns
     * @param array<int, array<string, mixed>> $rows
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function valuesSql(array $columns, array $rows): array
    {
        $groups = [];
        $params = [];
        foreach ($rows as $row) {
            $placeholders = [];
            foreach ($columns as $column) {
                $value = $row[$column];
                if ($value === null) {
                    $placeholders[] = 'NULL';
                    continue;
                }
                $placeholders[] = $this->placeholder($value);
                $params[] = $value;
            }
            $groups[] = '(' . implode(', ', $placeholders) . ')';
        }
        return [implode(', ', $groups), $params];
    }

    /** @param mixed $value */
    private function placeholder($value): string
    {
        Sql::scalar($value, false);
        if (is_bool($value) || is_int($value)) {
            return '%d';
        }
        if (is_float($value)) {
            return '%f';
        }
        return '%s';
    }

    /** @param mixed[] $params */
    private function executePrepared(string $sql, array $params): void
    {
        $prepared = $params === [] ? $sql : $this->adapter->prepare($sql, ...$params);
        $this->adapter->execute($prepared);
    }

    /** @param string[] $fields */
    private function quotedFields(array $fields): string
    {
        return implode(', ', array_map([Sql::class, 'quote'], $fields));
    }

    private function quotedTable(): string
    {
        return Sql::quote(Sql::table($this->table, $this->adapter->getPrefix()));
    }

    /**
     * @param mixed[] $fields
     * @return string[]
     */
    private function validateFieldList(array $fields, string $label, bool $allowEmpty): array
    {
        if (!self::isList($fields) || (!$allowEmpty && $fields === [])) {
            throw new \InvalidArgumentException($label . ' must be a list of field names.');
        }
        $seen = [];
        foreach ($fields as $field) {
            if (!is_string($field)) {
                throw new \InvalidArgumentException($label . ' must contain only field names.');
            }
            $this->assertField($field);
            if (isset($seen[$field])) {
                throw new \InvalidArgumentException($label . ' must not contain duplicates.');
            }
            $seen[$field] = true;
        }
        return array_values($fields);
    }

    /** @param mixed $field */
    private function assertField($field): void
    {
        if (!is_string($field)) {
            throw new \InvalidArgumentException('Column names must be strings.');
        }
        Sql::identifier($field);
        if ($this->allowedFields !== null && !isset($this->allowedFields[$field])) {
            throw new \InvalidArgumentException('Unknown field for schema-backed model: ' . $field);
        }
    }

    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
