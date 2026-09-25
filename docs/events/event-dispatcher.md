# Event dispatcher

`DatabaseEventDispatcher` supports `addListener($listener)`, `removeListener($listener)`, `getListeners()`, and `clearListeners()`. Each listener implements `DatabaseEventListener::handle(DatabaseEvent $event): void`.

Listeners run synchronously in registration order. Their errors are caught and logged so observational code cannot break the caller. Do not use that behavior for mandatory audit/outbox writes requiring atomicity. See [event semantics](overview.md).
