<?php

namespace Plasma\Adapter;

use PDO;
use Plasma\Internal\Sql;

/** PDO adapter for MySQL/MariaDB and SQLite. */
class PdoAdapter implements DatabaseAdapter
{
    private PDO $pdo;
    private string $prefix;
    private int $transactionDepth = 0;

    public function __construct(array $config)
    {
        $this->prefix = $config['prefix'] ?? '';
        Sql::table('validation', $this->prefix);
        $options = ($config['options'] ?? []) + [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        // Errors must not be mistaken for an empty successful result.
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $this->pdo = new PDO($config['dsn'] ?? '', $config['username'] ?? '', $config['password'] ?? '', $options);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        try {
            return $statement->columnCount() > 0 ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } finally {
            $statement->closeCursor();
        }
    }

    /**
     * WordPress-compatible SQL formatting, NOT PDO::prepare().
     * Values are quoted by this connection in a single pass; inserted values
     * are never reparsed as placeholders or regular-expression replacements.
     */
    public function prepare(string $sql, ...$params): string
    {
        preg_match_all('/%%|%[sdf]/', $sql, $matches);
        $expected = count(array_filter($matches[0], function ($token) { return $token !== '%%'; }));
        if ($expected !== count($params)) {
            throw new \InvalidArgumentException('SQL placeholder count does not match parameter count.');
        }
        $index = 0;
        return preg_replace_callback('/%%|%[sdf]/', function ($match) use ($params, &$index) {
            if ($match[0] === '%%') {
                return '%';
            }
            $value = $params[$index++];
            Sql::scalar($value);
            if ($value === null) {
                return 'NULL';
            }
            if ($match[0] === '%d') {
                if (!is_numeric($value) && !is_bool($value)) {
                    throw new \InvalidArgumentException('%d requires a numeric value.');
                }
                return (string) (int) $value;
            }
            if ($match[0] === '%f') {
                if (!is_numeric($value) || !is_finite((float) $value)) {
                    throw new \InvalidArgumentException('%f requires a finite numeric value.');
                }
                return json_encode((float) $value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            if (is_int($value) || is_float($value)) {
                return json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            }
            $quoted = $this->pdo->quote($value);
            if ($quoted === false) {
                throw new \RuntimeException('PDO driver cannot quote SQL values.');
            }
            return $quoted;
        }, $sql);
    }

    public function escapeLike(string $value): string
    {
        return addcslashes($value, '_%\\');
    }

    public function insert(string $table, array $data)
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Insert data must not be empty.');
        }
        Sql::row($data);
        $columns = array_map([Sql::class, 'quote'], array_keys($data));
        $sql = 'INSERT INTO ' . $this->table($table) . ' (' . implode(', ', $columns) . ') VALUES (' .
            implode(', ', array_fill(0, count($data), '?')) . ')';
        $statement = $this->executeStatement($sql, array_values($data));
        $statement->closeCursor();
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where)
    {
        Sql::equalityWhere($where);
        Sql::row($data);
        if ($data === []) {
            return 0;
        }
        $set = array_map(function ($column) { return Sql::quote($column) . ' = ?'; }, array_keys($data));
        $params = array_values($data);
        $sql = 'UPDATE ' . $this->table($table) . ' SET ' . implode(', ', $set) . $this->writeWhere($where, $params);
        $statement = $this->executeStatement($sql, $params);
        $count = $statement->rowCount();
        $statement->closeCursor();
        return $count;
    }

    public function delete(string $table, array $where)
    {
        Sql::equalityWhere($where);
        $params = [];
        $sql = 'DELETE FROM ' . $this->table($table) . $this->writeWhere($where, $params);
        $statement = $this->executeStatement($sql, $params);
        $count = $statement->rowCount();
        $statement->closeCursor();
        return $count;
    }

    private function table(string $table): string
    {
        return Sql::quote(Sql::table($table, $this->prefix));
    }

    private function writeWhere(array $where, array &$params): string
    {
        $parts = [];
        foreach ($where as $column => $value) {
            $parts[] = Sql::quote($column) . ($value === null ? ' IS NULL' : ' = ?');
            if ($value !== null) {
                $params[] = $value;
            }
        }
        return ' WHERE ' . implode(' AND ', $parts);
    }

    private function executeStatement(string $sql, array $params): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            Sql::scalar($value);
            if (is_bool($value)) {
                $value = (int) $value;
            }
            $type = $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $statement->bindValue($index + 1, $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    public function getVar(string $sql)
    {
        $statement = $this->pdo->query($sql);
        try {
            $row = $statement->fetch(PDO::FETCH_NUM);
            return $row === false ? null : $row[0];
        } finally {
            $statement->closeCursor();
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT LEVEL' . $this->transactionDepth);
        }
        $this->transactionDepth++;
    }

    public function commit(): void
    {
        $depth = $this->nextDepth();
        if ($depth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec('RELEASE SAVEPOINT LEVEL' . $depth);
        }
        $this->transactionDepth = $depth;
    }

    public function rollback(): void
    {
        $depth = $this->nextDepth();
        if ($depth === 0) {
            $this->pdo->rollBack();
        } else {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT LEVEL' . $depth);
            $this->pdo->exec('RELEASE SAVEPOINT LEVEL' . $depth);
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
    public function getPrefix(): string { return $this->prefix; }
}
