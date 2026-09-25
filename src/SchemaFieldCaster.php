<?php

namespace Plasma;

/**
 * Hydrate / serialize record fields according to Plasma schema.json types.
 */
class SchemaFieldCaster
{
    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $fieldTypes field name => schema type
     * @return array<string, mixed>
     */
    public static function hydrateRecord(array $row, array $fieldTypes): array
    {
        foreach ($fieldTypes as $field => $type) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $row[$field] = self::hydrateValue($row[$field], $type);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $fieldTypes
     * @return array<string, mixed>
     */
    public static function serializeRecord(array $data, array $fieldTypes): array
    {
        foreach ($fieldTypes as $field => $type) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $data[$field] = self::serializeValue($data[$field], $type);
        }

        return $data;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function hydrateValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \UnexpectedValueException('Invalid JSON stored in a JSON field.');
                    }
                    return $decoded;
                }
                if (is_object($value)) {
                    return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                }
                return $value;

            case 'int':
                return (int) $value;

            case 'bigint':
                return self::bigint($value);

            case 'float':
                return (float) $value;

            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }
                return in_array($value, [true, 1, '1', 'true'], true);

            case 'datetime':
                return (string) $value;

            case 'string':
            default:
                return $value;
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function serializeValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'json':
                if (is_string($value)) {
                    json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    return $value;
                }
                return json_encode($value, JSON_THROW_ON_ERROR);

            case 'int':
                return (int) $value;

            case 'bigint':
                return self::bigint($value);

            case 'float':
                return $value;

            case 'boolean':
                return self::hydrateValue($value, 'boolean') ? 1 : 0;

            case 'datetime':
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d H:i:s');
                }
                return (string) $value;

            case 'string':
            default:
                return $value;
        }
    }

    /** Preserve integers outside the host PHP integer range as strings. */
    private static function bigint($value)
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || !preg_match('/^-?[0-9]+$/D', $value)) {
            throw new \InvalidArgumentException('BigInt values must be integers or decimal integer strings.');
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        return $integer === false ? $value : $integer;
    }

    /**
     * @param array<string, array<string, mixed>> $schemaFields from schema.json model fields
     * @return array<string, string>
     */
    public static function fieldTypesFromSchema(array $schemaFields): array
    {
        $types = [];
        foreach ($schemaFields as $name => $def) {
            if (isset($def['type']) && !in_array($def['type'], ['hasMany', 'hasOne', 'belongsTo'], true)) {
                $types[$name] = $def['type'];
            }
        }

        return $types;
    }
}
