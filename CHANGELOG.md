# Changelog

## Unreleased — bulk writes, distinct values, and statement execution

### Added

- Add a dedicated `DatabaseAdapter::execute()` contract for non-row DDL/DML statements.
- Add `DialectAwareAdapter` and dialect forwarding through `EventDatabaseAdapter`.
- Add schema-aware `Model::createMany()` and `Model::upsertMany()` for bounded homogeneous batches.
- Add typed `Model::distinct()` scalar and tuple queries with filtering, ordering, and pagination.
- Add real MySQL/PDO and optional real WordPress `wpdb` regression coverage.

### Improved

- Forward adapter-provided default schemas through event decorators, preserving bundled WordPress models.
- Emit dedicated `statement` database events for `execute()` calls.
- Route reset/DDL paths and examples through `execute()` instead of treating statements as row queries.
- Make the runtime smoke portable across SQLite and externally configured MySQL/MariaDB.

### Compatibility notes

Custom `DatabaseAdapter` implementations must add `execute(string $sql): int`. Dialect-specific operations such as `upsertMany()` additionally require `DialectAwareAdapter`; unsupported dialects fail explicitly. Bulk methods return the number of input rows processed after successful execution rather than database-specific affected-row counts.

## 0.2.0 — 2026-09-25 — WordPress core schema

### Added

- Bundle the standard WordPress core data schema and load it automatically through `WpdbAdapter`.
- Built-in models for users, posts, metadata, comments, taxonomy, relationships, and options, including common relations.
- Schema-backed field allowlisting for filters, projections, sorting, creates, updates, and deletes.
- Correct WordPress multisite prefix handling: site tables use `$wpdb->prefix`; users/usermeta use `$wpdb->base_prefix`.

### Improved

- Preserve absolute WordPress core table names through the model/query/adapter boundary even when an application suffix is configured.
- WordPress docs now focus on schema-aware ORM access without hand-written `esc_sql()`, `$wpdb->prepare()`, LIKE escaping, or prefix concatenation for supported Plasma queries.

## 0.1.0 — Initial public release

### Added

- Prisma-inspired scalar query API with recursive AND/OR/NOT, null handling, projection, sorting, and pagination.
- PHP-array schema registration and strict JSON-file loading for runtime model metadata.
- Public documentation, runnable SQLite example, contributing/security guides, and MIT licensing.
- Database-backed regression tests covering CRUD, relationships, escaping, transactions, and schema metadata.

### Fixed and hardened

- Normalize logical and physical table prefixes once across reads and writes.
- Replace recursive PDO string substitution with a validated single-pass formatter; use bound parameters for PDO writes.
- Reject malformed predicates, unsafe identifiers, unbounded filter structures, and unsupported mutation filters.
- Correct belongsTo foreign-key direction, related primary-key resolution, filtered includes, disabled includes, and relation projections.
- Handle zero/offset-only pagination and Prisma-style boolean select maps.
- Raise database failures instead of returning apparent empty success.
- Roll back on PHP Errors, preserve transaction depth after failed SQL commands, and repair event getVar delegation.
- Keep oversized integers lossless and reject invalid JSON.
- Remove PHP 8-only syntax from the event layer while preserving immutable event payloads.

### Compatibility notes

This is pre-1.0 software. Invalid inputs previously ignored now raise exceptions. Writes remain equality-only. Metadata reload invalidates cached model handles. Per-parent include pagination is rejected rather than applied globally. Missing/invalid schema files no longer silently fall back to dynamic models.
