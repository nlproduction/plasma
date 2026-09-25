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

    public function __construct(string $table, DatabaseAdapter $adapter, array $limits = [])
    {
        $this->table = Sql::quote(Sql::table($table, $adapter->getPrefix()));
        $this->adapter = $adapter;
        $this->compiler = new WhereCompiler($adapter, $limits);
    }

    public function execute(array $options = []): array
    {
        $this->validateOptions($options);
        $sql = 'SELECT ' . $this->buildSelect($options['select'] ?? []) . ' FROM ' . $this->table;
        $sql .= $this->buildWhere($options['where'] ?? []);
        $sql .= $this->buildOrderBy($options['orderBy'] ?? []);
        if (array_key_exists('take', $options)) {
            $sql .= ' LIMIT ' . $options['take'];
        } elseif (!empty($options['skip'])) {
            // Valid for both MySQL/MariaDB and SQLite; OFFSET cannot stand alone.
            $sql .= ' LIMIT 9223372036854775807';
        }
        if (!empty($options['skip'])) {
            $sql .= ' OFFSET ' . $options['skip'];
        }
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
                Sql::identifier($field);
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
            return Sql::quote($field);
        }, $fields));
    }

    public function buildWhere(array $where): string
    {
        return $this->compiler->compile($where);
    }

    public function buildOrderBy(array $orderBy): string
    {
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
