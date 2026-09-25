<?php

namespace Plasma\Adapter;

/**
 * Database Adapter Interface
 * 
 * Abstraction layer for different database implementations
 * Compatible with PHP 7.4+ (WordPress requirement)
 */
interface DatabaseAdapter
{
  /**
   * Execute SELECT query and return results
   * 
   * @param string $sql
   * @return array
   */
  public function query(string $sql): array;

  /**
   * Format SQL with connection-escaped values (WordPress-style %s/%d/%f)
   * 
   * @param string $sql
   * @param mixed ...$params
   * @return string
   */
  public function prepare(string $sql, ...$params): string;

  /**
   * Escape string for LIKE queries
   * 
   * @param string $value
   * @return string
   */
  public function escapeLike(string $value): string;

  /**
   * Insert a row, return its generated integer identifier when available
   * 
   * @param string $table
   * @param array $data
   * @return int|false Generated integer ID (or adapter result for an explicit key); false for a custom adapter failure
   */
  public function insert(string $table, array $data);

  /**
   * Update rows, return affected count
   * 
   * @param string $table
   * @param array $data
   * @param array $where
   * @return int|false Returns affected rows count on success, false on failure
   */
  public function update(string $table, array $data, array $where);

  /**
   * Delete rows, return affected count
   * 
   * @param string $table
   * @param array $where
   * @return int|false Returns affected rows count on success, false on failure
   */
  public function delete(string $table, array $where);

  /**
   * Get single value
   * 
   * @param string $sql
   * @return mixed
   */
  public function getVar(string $sql);

  /**
   * Transaction methods
   */
  public function beginTransaction(): void;
  public function commit(): void;
  public function rollback(): void;

  /**
   * Check if transaction is currently active
   * 
   * @return bool
   */
  public function inTransaction(): bool;

  /**
   * Get current transaction depth (nesting level)
   * 0 = no transaction, 1 = top-level, 2+ = nested (savepoints)
   * 
   * @return int
   */
  public function getTransactionDepth(): int;

  /**
   * Get table prefix
   * 
   * @return string
   */
  public function getPrefix(): string;
}
