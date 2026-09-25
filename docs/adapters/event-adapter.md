# Event adapter

Wrap an existing adapter with `EventDatabaseAdapter` to observe reads and writes. The wrapper preserves prefix, formatting, and transaction behavior. `getVar()` delegates to the correctly named method and emits a query event.

See [events and listeners](../events/overview.md) for a runnable setup. Listeners are best-effort observers: exceptions are isolated and logged. They are not suitable for guarantees that require rolling back the original write.
