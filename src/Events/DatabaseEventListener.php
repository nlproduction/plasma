<?php

namespace Plasma\Events;

/**
 * Database Event Listener Interface
 * 
 * Implement this to subscribe to database events
 */
interface DatabaseEventListener
{
  public function handle(DatabaseEvent $event): void;
}
