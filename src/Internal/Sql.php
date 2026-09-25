<?php

namespace Plasma\Internal;

/** @internal Shared SQL boundary checks; not an authorization policy. */
final class Sql
{
    public static function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier.');
        }
        return $name;
    }

    public static function quote(string $name): string
    {
        return '`' . self::identifier($name) . '`';
    }

    public static function table(string $name, string $prefix): string
    {
        if (strpos($name, '__PREFIX__') === 0) {
            $name = $prefix . substr($name, 10);
        } elseif ($prefix !== '' && strpos($name, $prefix) !== 0) {
            $name = $prefix . $name;
        }
        return self::identifier($name);
    }

    public static function scalar($value, bool $nullable = true): void
    {
        if (($value === null && $nullable) ||
            (is_scalar($value) && (!is_float($value) || is_finite($value)))) {
            return;
        }
        throw new \InvalidArgumentException('SQL values must be finite scalars' . ($nullable ? ' or null.' : '.'));
    }

    /** Validate the intentionally small equality-only mutation contract. */
    public static function equalityWhere(array $where): void
    {
        if ($where === []) {
            throw new \InvalidArgumentException('A non-empty equality WHERE is required for writes.');
        }
        foreach ($where as $column => $value) {
            if (!is_string($column) || in_array($column, ['AND', 'OR', 'NOT'], true)) {
                throw new \InvalidArgumentException('Writes require field => scalar/null equality filters.');
            }
            self::identifier($column);
            self::scalar($value);
        }
    }

    public static function row(array $data): void
    {
        foreach ($data as $column => $value) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException('Column names must be strings.');
            }
            self::identifier($column);
            self::scalar($value);
        }
    }
}
