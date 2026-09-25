<?php

namespace Plasma\Adapter;

use Plasma\Events\DatabaseEvent;
use Plasma\Events\DatabaseEventDispatcher;

/** Opt-in observation decorator. Listeners are not transactional middleware. */
class EventDatabaseAdapter implements DatabaseAdapter, SchemaProvidingAdapter, DialectAwareAdapter
{
    private DatabaseAdapter $adapter;
    private DatabaseEventDispatcher $dispatcher;

    public function __construct(DatabaseAdapter $adapter, ?DatabaseEventDispatcher $dispatcher = null)
    {
        $this->adapter = $adapter;
        $this->dispatcher = $dispatcher ?? new DatabaseEventDispatcher();
    }

    public function getDispatcher(): DatabaseEventDispatcher { return $this->dispatcher; }

    private function observe(string $method, array $args)
    {
        $start = microtime(true);
        try {
            $result = $this->adapter->{$method}(...$args);
            if ($result === false && in_array($method, ['execute', 'insert', 'update', 'delete'], true)) {
                throw new \RuntimeException('Database adapter reported a failed write.');
            }
        } catch (\Throwable $error) {
            $this->dispatch($method, $args, null, $start, $error);
            throw $error;
        }
        $this->dispatch($method, $args, $result, $start);
        return $result;
    }

    private function dispatch(string $method, array $args, $result, float $start, ?\Throwable $error = null): void
    {
        $sqlMethod = in_array($method, ['query', 'getVar', 'execute'], true);
        $operation = $method === 'execute'
            ? 'statement'
            : ($sqlMethod ? 'query' : ($method === 'insert' ? 'create' : $method));

        $this->dispatcher->dispatch(new DatabaseEvent(
            $operation,
            $sqlMethod ? '' : $args[0],
            in_array($method, ['insert', 'update'], true) ? $args[1] : null,
            $method === 'update' ? $args[2] : ($method === 'delete' ? $args[1] : null),
            $result,
            (microtime(true) - $start) * 1000,
            $sqlMethod ? $args[0] : null,
            $error
        ));
    }

    public function query(string $sql): array { return $this->observe('query', [$sql]); }
    public function execute(string $sql): int { return $this->observe('execute', [$sql]); }
    public function insert(string $table, array $data) { return $this->observe('insert', [$table, $data]); }
    public function update(string $table, array $data, array $where) { return $this->observe('update', [$table, $data, $where]); }
    public function delete(string $table, array $where) { return $this->observe('delete', [$table, $where]); }
    public function getVar(string $sql) { return $this->observe('getVar', [$sql]); }
    public function prepare(string $sql, ...$params): string { return $this->adapter->prepare($sql, ...$params); }
    public function escapeLike(string $value): string { return $this->adapter->escapeLike($value); }
    public function beginTransaction(): void { $this->adapter->beginTransaction(); }
    public function commit(): void { $this->adapter->commit(); }
    public function rollback(): void { $this->adapter->rollback(); }
    public function inTransaction(): bool { return $this->adapter->inTransaction(); }
    public function getTransactionDepth(): int { return $this->adapter->getTransactionDepth(); }
    public function getPrefix(): string { return $this->adapter->getPrefix(); }

    public function defaultSchemas(): array
    {
        return $this->adapter instanceof SchemaProvidingAdapter
            ? $this->adapter->defaultSchemas()
            : [];
    }

    public function getDialect(): string
    {
        if (!$this->adapter instanceof DialectAwareAdapter) {
            throw new \RuntimeException('Wrapped database adapter does not expose its SQL dialect.');
        }
        return $this->adapter->getDialect();
    }
}
