<?php

namespace Plasma\Events;

/**
 * Database Event Dispatcher
 * 
 * Manages listeners and dispatches events
 */
class DatabaseEventDispatcher
{
  private array $listeners = [];

  /**
   * Add a listener
   */
  public function addListener(DatabaseEventListener $listener): void
  {
    $this->listeners[] = $listener;
  }

  /**
   * Remove a listener
   */
  public function removeListener(DatabaseEventListener $listener): void
  {
    $this->listeners = array_filter(
      $this->listeners,
      fn($l) => $l !== $listener
    );
  }

  /**
   * Dispatch event to all listeners
   */
  public function dispatch(DatabaseEvent $event): void
  {
    foreach ($this->listeners as $listener) {
      try {
        $listener->handle($event);
      } catch (\Throwable $e) {
        // Log listener errors but don't break the chain
        if (function_exists('error_log')) {
          error_log("Database event listener error: " . $e->getMessage());
        }
      }
    }
  }

  /**
   * Get all registered listeners
   */
  public function getListeners(): array
  {
    return $this->listeners;
  }

  /**
   * Clear all listeners
   */
  public function clearListeners(): void
  {
    $this->listeners = [];
  }
}
