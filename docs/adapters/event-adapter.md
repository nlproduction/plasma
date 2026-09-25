# Event adapter

Wrap an existing adapter with `EventDatabaseAdapter` to observe reads and writes. The wrapper preserves prefix, formatting, transaction behavior, adapter-provided schemas, and SQL dialect capability. In particular, wrapping `WpdbAdapter` does not hide the bundled WordPress core models.

`query()` and `getVar()` emit `query` events. `execute()` emits a `statement` event with the trusted SQL text and returned affected-row count. Insert/update/delete keep their existing create/update/delete event names. Preparation and LIKE escaping are helper operations and do not emit events.

If the wrapped adapter does not implement `SchemaProvidingAdapter`, the decorator supplies no default schemas. If dialect-specific functionality such as `upsertMany()` asks for a dialect but the wrapped adapter does not implement `DialectAwareAdapter`, the decorator fails explicitly instead of guessing.

See [events and listeners](../events/overview.md) for a runnable setup. Listeners are best-effort observers: exceptions are isolated and logged. They are not suitable for guarantees that require rolling back the original write.
