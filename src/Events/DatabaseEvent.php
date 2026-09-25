<?php

namespace Plasma\Events;

/**
 * Immutable event payload, including on PHP 7.4.
 * @property-read string $operation
 * @property-read string $table
 * @property-read array|null $data
 * @property-read array|null $where
 * @property-read mixed $result
 * @property-read float $duration Milliseconds
 * @property-read string|null $sql
 * @property-read \Throwable|null $error
 */
class DatabaseEvent implements \JsonSerializable
{
    private array $values;

    public function __construct(
        string $operation,
        string $table,
        ?array $data = null,
        ?array $where = null,
        $result = null,
        float $duration = 0,
        ?string $sql = null,
        ?\Throwable $error = null
    ) {
        $this->values = compact('operation', 'table', 'data', 'where', 'result', 'duration', 'sql', 'error');
    }

    public function __get(string $name)
    {
        if (!array_key_exists($name, $this->values)) {
            throw new \OutOfBoundsException('Unknown event property: ' . $name);
        }
        return $this->values[$name];
    }

    public function __isset(string $name): bool { return isset($this->values[$name]); }
    public function __set(string $name, $value): void { throw new \LogicException('Database events are immutable.'); }
    public function __unset(string $name): void { throw new \LogicException('Database events are immutable.'); }
    public function jsonSerialize(): array { return $this->values; }
}
