# Changelog

## 0.1.0 — Initial public release

### Added

- Prisma-inspired scalar query API with recursive AND/OR/NOT, null handling, projection, sorting, and pagination.
- PHP-array schema registration and strict JSON-file loading; Node.js and code generation are not required.
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
