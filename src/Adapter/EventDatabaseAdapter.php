<?php

namespace Plasma\Adapter;

use Plasma\Events\DatabaseEvent;
use Plasma\Events\DatabaseEventDispatcher;

/** Opt-in observation decorator. Listeners are not transactional middleware. */
class EventDatabaseAdapter implements DatabaseAdapter
{
    private DatabaseAdapter $adapter;
    private DatabaseEventDispatcher $dispatcher;

    public function __construct(DatabaseAdapter $adapter, ?DatabaseEventDispatcher $dispatcher = null)
    {
        $this->adapter = $adapter;
        $this->dispatcher = $dispatcher ?? new DatabaseEventDispatcher();
    }

    public function getDispatcher(): DatabaseEventDispatcher { return $this->dispatcher; }

    private function execute(string $method, array $args)
    {
        $start = microtime(true);
        try {
            $result = $this->adapter->{$method}(...$args);
            if ($result === false && in_array($method, ['insert', 'update', 'delete'], true)) {
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
        $query = $method === 'query' || $method === 'getVar';
        $this->dispatcher->dispatch(new DatabaseEvent(
            $query ? 'query' : ($method === 'insert' ? 'create' : $method),
            $query ? '' : $args[0],
            in_array($method, ['insert', 'update'], true) ? $args[1] : null,
            $method === 'update' ? $args[2] : ($method === 'delete' ? $args[1] : null),
            $result,
            (microtime(true) - $start) * 1000,
            $query ? $args[0] : null,
            $error
        ));
    }

    public function query(string $sql): array { return $this->execute('query', [$sql]); }
    public function insert(string $table, array $data) { return $this->execute('insert', [$table, $data]); }
    public function update(string $table, array $data, array $where) { return $this->execute('update', [$table, $data, $where]); }
    public function delete(string $table, array $where) { return $this->execute('delete', [$table, $where]); }
    public function getVar(string $sql) { return $this->execute('getVar', [$sql]); }
    public function prepare(string $sql, ...$params): string { return $this->adapter->prepare($sql, ...$params); }
    public function escapeLike(string $value): string { return $this->adapter->escapeLike($value); }
    public function beginTransaction(): void { $this->adapter->beginTransaction(); }
    public function commit(): void { $this->adapter->commit(); }
    public function rollback(): void { $this->adapter->rollback(); }
    public function inTransaction(): bool { return $this->adapter->inTransaction(); }
    public function getTransactionDepth(): int { return $this->adapter->getTransactionDepth(); }
    public function getPrefix(): string { return $this->adapter->getPrefix(); }
}
