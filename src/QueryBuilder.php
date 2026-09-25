<?php

namespace Plasma;

use Plasma\Adapter\DatabaseAdapter;
use Plasma\Internal\Sql;
use Plasma\Internal\WhereCompiler;

/** Assembles validated single-table read queries. */
class QueryBuilder
{
    protected string $table;
    protected DatabaseAdapter $adapter;
    private WhereCompiler $compiler;
    /** @var array<string, true>|null */
    private $allowedFields;

    public function __construct(string $table, DatabaseAdapter $adapter, array $limits = [], ?array $allowedFields = null)
    {
        $this->table = Sql::quote(Sql::table($table, $adapter->getPrefix()));
        $this->adapter = $adapter;
        if ($allowedFields !== null) {
            foreach ($allowedFields as $field) {
                if (!is_string($field)) {
                    throw new \InvalidArgumentException('Allowed fields must be strings.');
                }
                Sql::identifier($field);
            }
        }
        $this->allowedFields = $allowedFields === null ? null : array_fill_keys($allowedFields, true);
        $this->compiler = new WhereCompiler($adapter, $limits, $allowedFields);
    }

    public function execute(array $options = []): array
    {
        $this->validateOptions($options);
        $sql = 'SELECT ' . $this->buildSelect($options['select'] ?? []) . ' FROM ' . $this->table;
        $sql .= $this->buildWhere($options['where'] ?? []);
        $sql .= $this->buildOrderBy($options['orderBy'] ?? []);
        $sql .= $this->buildPagination($options);
        return $this->adapter->query($sql);
    }

    /**
     * Return unique rows for one or more selected fields.
     *
     * Supported options: where, orderBy, take, skip. Order fields must be part
     * of the distinct field list for portable MySQL/SQLite behavior.
     */
    public function distinct(array $fields, array $options = []): array
    {
        if (!WhereCompiler::isList($fields) || $fields === []) {
            throw new \InvalidArgumentException('distinct fields must be a non-empty list.');
        }

        $seen = [];
        foreach ($fields as $field) {
            if (!is_string($field)) {
                throw new \InvalidArgumentException('distinct fields must contain only names.');
            }
            $this->assertField($field);
            if (isset($seen[$field])) {
                throw new \InvalidArgumentException('distinct fields must not contain duplicates.');
            }
            $seen[$field] = true;
        }

        $this->validateOptions($options);
        if (array_key_exists('select', $options) || array_key_exists('include', $options)) {
            throw new \InvalidArgumentException('distinct does not accept select or include.');
        }

        $sql = 'SELECT DISTINCT ' . $this->buildSelect($fields) . ' FROM ' . $this->table;
        $sql .= $this->buildWhere($options['where'] ?? []);
        $sql .= $this->buildOrderBy($options['orderBy'] ?? [], $fields);
        $sql .= $this->buildPagination($options);

        return $this->adapter->query($sql);
    }

    public function buildSelect(array $select): string
    {
        if ($select === []) {
            return '*';
        }
        $fields = [];
        if (WhereCompiler::isList($select)) {
            $fields = $select;
        } else {
            foreach ($select as $field => $enabled) {
                if (!is_string($field) || !is_bool($enabled)) {
                    throw new \InvalidArgumentException('select must be a list of names or a field => boolean map.');
                }
                $this->assertField($field);
                if ($enabled) {
                    $fields[] = $field;
                }
            }
        }
        if ($fields === []) {
            throw new \InvalidArgumentException('select must enable at least one field.');
        }
        return implode(', ', array_map(function ($field) {
            if (!is_string($field)) {
                throw new \InvalidArgumentException('SELECT field names must be strings.');
            }
            $this->assertField($field);
            return Sql::quote($field);
        }, $fields));
    }

    public function buildWhere(array $where): string
    {
        return $this->compiler->compile($where);
    }

    public function buildOrderBy(array $orderBy, ?array $onlyFields = null): string
    {
        $only = null;
        if ($onlyFields !== null) {
            foreach ($onlyFields as $field) {
                if (!is_string($field)) {
                    throw new \InvalidArgumentException('Allowed order fields must be strings.');
                }
                $this->assertField($field);
            }
            $only = array_fill_keys($onlyFields, true);
        }

        $parts = [];
        $groups = WhereCompiler::isList($orderBy) ? $orderBy : [$orderBy];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                throw new \InvalidArgumentException('orderBy requires objects.');
            }
            foreach ($group as $field => $direction) {
                if (!is_string($field) || !is_string($direction) || !in_array(strtoupper($direction), ['ASC', 'DESC'], true)) {
                    throw new \InvalidArgumentException('orderBy requires field names and asc/desc directions.');
                }
                $this->assertField($field);
                if ($only !== null && !isset($only[$field])) {
                    throw new \InvalidArgumentException('orderBy fields must be selected by distinct.');
                }
                $parts[] = Sql::quote($field) . ' ' . strtoupper($direction);
            }
        }
        return $parts === [] ? '' : ' ORDER BY ' . implode(', ', $parts);
    }

    /** Count all matching rows, independently of pagination. */
    public function count(array $options = []): int
    {
        $this->validateOptions($options);
        return (int) $this->adapter->getVar('SELECT COUNT(*) FROM ' . $this->table . $this->buildWhere($options['where'] ?? []));
    }

    private function buildPagination(array $options): string
    {
        $sql = '';
        if (array_key_exists('take', $options)) {
            $sql .= ' LIMIT ' . $options['take'];
        } elseif (!empty($options['skip'])) {
            // Valid for both MySQL/MariaDB and SQLite; OFFSET cannot stand alone.
            $sql .= ' LIMIT 9223372036854775807';
        }
        if (!empty($options['skip'])) {
            $sql .= ' OFFSET ' . $options['skip'];
        }
        return $sql;
    }


    private function assertField(string $field): void
    {
        Sql::identifier($field);
        if ($this->allowedFields !== null && !isset($this->allowedFields[$field])) {
            throw new \InvalidArgumentException('Unknown field for schema-backed model: ' . $field);
        }
    }

    private function validateOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            if (in_array($key, ['take', 'skip'], true)) {
                if (!is_int($value) || $value < 0) {
                    throw new \InvalidArgumentException('take and skip must be non-negative integers.');
                }
            } elseif (in_array($key, ['select', 'where', 'orderBy', 'include'], true)) {
                if (!is_array($value)) {
                    throw new \InvalidArgumentException($key . ' must be an array/object decoded as a PHP array.');
                }
            } else {
                throw new \InvalidArgumentException('Unsupported query option: ' . $key);
            }
        }
    }
}
