# Database events

Events are opt-in. Without the decorator, queries have no event dispatch overhead.

```php
use Plasma\Adapter\EventDatabaseAdapter;
use Plasma\Events\DatabaseEvent;
use Plasma\Events\DatabaseEventDispatcher;
use Plasma\Events\DatabaseEventListener;
use Plasma\Plasma;

$dispatcher = new DatabaseEventDispatcher();
$dispatcher->addListener(new class implements DatabaseEventListener {
    public function handle(DatabaseEvent $event): void
    {
        // Record timings, invalidate application caches, or observe failures.
        // $event->duration is milliseconds; redact sensitive values before logging.
    }
});
$db = new Plasma(new EventDatabaseAdapter($baseAdapter, $dispatcher));
```

An immutable event exposes `operation`, `table`, `data`, `where`, `result`, `duration`, `sql`, and `error`. SQL is included for `query`, `getVar`, and `execute` operations. Non-row `execute()` calls use operation `statement`; row reads use `query`. Failed writes/statements emit an error event and rethrow; a false result from a custom adapter is not reported as success.

Observer exceptions are isolated by the dispatcher. This is deliberate for telemetry, **not a transactional outbox guarantee**. Security checks and mandatory business invariants belong before the write, not in these listeners. Avoid writing through the same observed adapter inside a listener, which can recurse indefinitely.

[Dispatcher API](event-dispatcher.md) · [Optional logging](../../src/Logging/README.md)
