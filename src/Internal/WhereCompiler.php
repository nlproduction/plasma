<?php

namespace Plasma\Internal;

use Plasma\Adapter\DatabaseAdapter;

/** @internal Compiles the documented scalar subset of Prisma-style filters. */
final class WhereCompiler
{
    private DatabaseAdapter $adapter;
    private int $nodes = 0;
    private array $limits;
    /** @var array<string, true>|null */
    private $allowedFields;

    public function __construct(DatabaseAdapter $adapter, array $limits = [], ?array $allowedFields = null)
    {
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
        $this->limits = $limits + ['maxDepth' => 64, 'maxConditions' => 1000, 'maxListValues' => 1000];
        foreach ($this->limits as $name => $value) {
            if (!in_array($name, ['maxDepth', 'maxConditions', 'maxListValues'], true) || !is_int($value) || $value < 1) {
                throw new \InvalidArgumentException('Query limits must be positive integers with known names.');
            }
        }
    }

    public function compile(array $where): string
    {
        $this->nodes = 0;
        return $where === [] ? '' : ' WHERE ' . $this->predicate($where, 0);
    }

    private function budget(int $depth): void
    {
        if ($depth > $this->limits['maxDepth'] || ++$this->nodes > $this->limits['maxConditions']) {
            throw new \InvalidArgumentException('Filter exceeds query complexity limits.');
        }
    }

    private function predicate(array $predicate, int $depth): string
    {
        $this->budget($depth);
        $parts = [];
        foreach ($predicate as $field => $value) {
            if (!is_string($field)) {
                throw new \InvalidArgumentException('Predicates must be objects with named fields.');
            }
            if (in_array($field, ['AND', 'OR', 'NOT'], true)) {
                $parts[] = $this->booleanGroup($field, $value, $depth + 1);
            } else {
                $this->assertField($field);
                $parts[] = $this->field(Sql::quote($field), $value, $depth + 1);
            }
        }
        return $this->join($parts, 'AND');
    }

    private function booleanGroup(string $operator, $value, int $depth): string
    {
        $this->budget($depth);
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Boolean groups require predicate objects or lists.');
        }
        if ($value === []) {
            return $operator === 'OR' ? '0 = 1' : '1 = 1';
        }
        $children = self::isList($value) ? $value : [$value];
        $parts = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                throw new \InvalidArgumentException('Boolean group children must be predicate objects.');
            }
            $sql = $this->predicate($child, $depth + 1);
            $parts[] = $operator === 'NOT' && $child !== [] ? 'NOT (' . $sql . ')' : $sql;
        }
        return $this->join($parts, $operator === 'OR' ? 'OR' : 'AND');
    }

    private function field(string $column, $value, int $depth): string
    {
        $this->budget($depth);
        if (!is_array($value)) {
            return $this->equality($column, $value, false);
        }
        if ($value === []) {
            throw new \InvalidArgumentException('Field operator objects must not be empty.');
        }
        $parts = [];
        foreach ($value as $operator => $operand) {
            if (!is_string($operator)) {
                throw new \InvalidArgumentException('Field operators must have names.');
            }
            $parts[] = $this->operator($column, $operator, $operand, $depth + 1);
        }
        return $this->join($parts, 'AND');
    }

    private function operator(string $column, string $operator, $value, int $depth): string
    {
        $this->budget($depth);
        if ($operator === 'equals') {
            return $this->equality($column, $value, false);
        }
        if ($operator === 'not') {
            if (is_array($value)) {
                return $value === [] ? '1 = 1' : 'NOT (' . $this->field($column, $value, $depth + 1) . ')';
            }
            return $this->equality($column, $value, true);
        }
        if ($operator === 'in' || $operator === 'notIn') {
            if (!is_array($value) || !self::isList($value) || count($value) > $this->limits['maxListValues']) {
                throw new \InvalidArgumentException('IN filters require a bounded list of scalar values.');
            }
            foreach ($value as $item) {
                Sql::scalar($item, false);
            }
            if ($value === []) {
                return $operator === 'in' ? '0 = 1' : '1 = 1';
            }
            $sql = $column . ($operator === 'in' ? ' IN (' : ' NOT IN (') . implode(',', array_fill(0, count($value), '%s')) . ')';
            return $this->adapter->prepare($sql, ...$value);
        }
        $comparisons = ['gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];
        if (isset($comparisons[$operator])) {
            Sql::scalar($value, false);
            return $this->adapter->prepare($column . ' ' . $comparisons[$operator] . ' %s', $value);
        }
        if (in_array($operator, ['contains', 'startsWith', 'endsWith'], true)) {
            Sql::scalar($value, false);
            $pattern = ($operator !== 'startsWith' ? '%' : '') . $this->adapter->escapeLike((string) $value) .
                ($operator !== 'endsWith' ? '%' : '');
            return $this->adapter->prepare($column . ' LIKE %s ESCAPE %s', $pattern, '\\');
        }
        throw new \InvalidArgumentException('Unsupported field operator: ' . $operator);
    }

    private function equality(string $column, $value, bool $negated): string
    {
        Sql::scalar($value);
        if ($value === null) {
            return $column . ($negated ? ' IS NOT NULL' : ' IS NULL');
        }
        return $this->adapter->prepare($column . ($negated ? ' != %s' : ' = %s'), $value);
    }

    private function join(array $parts, string $operator): string
    {
        if ($parts === []) {
            return $operator === 'OR' ? '0 = 1' : '1 = 1';
        }
        return count($parts) === 1 ? $parts[0] : '(' . implode(' ' . $operator . ' ', $parts) . ')';
    }


    private function assertField(string $field): void
    {
        Sql::identifier($field);
        if ($this->allowedFields !== null && !isset($this->allowedFields[$field])) {
            throw new \InvalidArgumentException('Unknown field for schema-backed model: ' . $field);
        }
    }

    public static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
