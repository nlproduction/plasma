<?php

namespace Plasma\WordPress;

use Plasma\Adapter\DatabaseAdapter;
use Plasma\Adapter\SchemaProvidingAdapter;
use Plasma\Internal\Sql;

/** WordPress adapter; the application supplies its own prefix suffix. */
class WpdbAdapter implements DatabaseAdapter, SchemaProvidingAdapter
{
    /** @var \wpdb */
    private $wpdb;
    private string $tablePrefix;
    private int $transactionDepth = 0;

    public function __construct(string $tablePrefix = '', $wpdbInstance = null)
    {
        if ($wpdbInstance === null) {
            global $wpdb;
            $wpdbInstance = $wpdb;
        }
        if (!is_object($wpdbInstance)) {
            throw new \RuntimeException('WordPress $wpdb is not available.');
        }
        $this->wpdb = $wpdbInstance;
        $this->tablePrefix = $tablePrefix;
        Sql::table('validation', $this->getPrefix());
    }

    public function query(string $sql): array
    {
        $rows = $this->wpdb->get_results($sql, 'ARRAY_A');
        $this->checkError($rows);
        return $rows ?: [];
    }

    public function prepare(string $sql, ...$params): string
    {
        foreach ($params as &$value) {
            Sql::scalar($value);
            if (is_bool($value)) {
                $value = (int) $value;
            }
        }
        unset($value);
        $prepared = $params === [] ? $sql : $this->wpdb->prepare($sql, ...$params);
        if (!is_string($prepared) || $prepared === '') {
            throw new \InvalidArgumentException('WordPress could not prepare the SQL statement.');
        }
        return $prepared;
    }

    public function escapeLike(string $value): string { return $this->wpdb->esc_like($value); }

    public function insert(string $table, array $data)
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Insert data must not be empty.');
        }
        Sql::row($data);
        $result = $this->wpdb->insert($this->table($table), $this->normalize($data));
        $this->checkError($result);
        return $this->wpdb->insert_id > 0 ? (int) $this->wpdb->insert_id : (int) $result;
    }

    public function update(string $table, array $data, array $where)
    {
        Sql::equalityWhere($where);
        Sql::row($data);
        if ($data === []) {
            return 0;
        }
        $result = $this->wpdb->update($this->table($table), $this->normalize($data), $this->normalize($where));
        $this->checkError($result);
        return (int) $result;
    }

    public function delete(string $table, array $where)
    {
        Sql::equalityWhere($where);
        $result = $this->wpdb->delete($this->table($table), $this->normalize($where));
        $this->checkError($result);
        return (int) $result;
    }

    public function getVar(string $sql)
    {
        $value = $this->wpdb->get_var($sql);
        $this->checkError($value);
        return $value;
    }

    private function table(string $name): string { return Sql::table($name, $this->getPrefix()); }

    private function normalize(array $values): array
    {
        return array_map(function ($value) { return is_bool($value) ? (int) $value : $value; }, $values);
    }

    private function checkError($result): void
    {
        if ($result === false || !empty($this->wpdb->last_error)) {
            throw new \RuntimeException('WordPress database operation failed.');
        }
    }

    private function transactionCommand(string $sql): void
    {
        $this->checkError($this->wpdb->query($sql));
    }

    public function beginTransaction(): void
    {
        $this->transactionCommand($this->transactionDepth === 0 ? 'START TRANSACTION' : 'SAVEPOINT LEVEL' . $this->transactionDepth);
        $this->transactionDepth++;
    }

    public function commit(): void
    {
        $depth = $this->nextDepth();
        $this->transactionCommand($depth === 0 ? 'COMMIT' : 'RELEASE SAVEPOINT LEVEL' . $depth);
        $this->transactionDepth = $depth;
    }

    public function rollback(): void
    {
        $depth = $this->nextDepth();
        $this->transactionCommand($depth === 0 ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT LEVEL' . $depth);
        if ($depth > 0) {
            $this->transactionCommand('RELEASE SAVEPOINT LEVEL' . $depth);
        }
        $this->transactionDepth = $depth;
    }

    private function nextDepth(): int
    {
        if ($this->transactionDepth === 0) {
            throw new \RuntimeException('No active transaction.');
        }
        return $this->transactionDepth - 1;
    }

    public function inTransaction(): bool { return $this->transactionDepth > 0; }
    public function getTransactionDepth(): int { return $this->transactionDepth; }
    public function getPrefix(): string { return $this->wpdb->prefix . $this->tablePrefix; }

    /** WordPress core models are available automatically to every Plasma client using this adapter. */
    public function defaultSchemas(): array
    {
        $basePrefix = isset($this->wpdb->base_prefix) ? $this->wpdb->base_prefix : $this->wpdb->prefix;
        return [WordPressSchema::load($this->wpdb->prefix, $basePrefix)];
    }
}
