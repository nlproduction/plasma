<?php

namespace Plasma\Logging;

use Plasma\Events\{DatabaseEvent, DatabaseEventListener};
use Plasma\Logging\Logger;

/**
 * Clockwork Database Listener
 * 
 * Logs all database queries to Clockwork
 */
class ClockworkListener implements DatabaseEventListener
{
  public function handle(DatabaseEvent $event): void
  {
    // Log using static Logger (old style) or direct clockwork()
    if (Logger::clockworkEnabled() && function_exists('clock')) {
      $sql = $event->sql ?? $this->formatSql($event);
      $context = $event->error ? ['error' => $event->error->getMessage()] : [];

      clock()->addDatabaseQuery($sql, [], $event->duration, $context);
    }
  }

  /**
   * Format SQL from event data
   */
  private function formatSql(DatabaseEvent $event): string
  {
    $sql = strtoupper($event->operation) . ' ';

    if ($event->table) {
      $sql .= $event->table;
    }

    if ($event->data) {
      $sql .= ' ' . json_encode($event->data);
    }

    if ($event->where) {
      $sql .= ' WHERE ' . json_encode($event->where);
    }

    return $sql;
  }
}
