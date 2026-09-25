# Security

Plasma is an early-stage database library, not an API authorization system or a claim of a completed security audit.

## Trust boundary

Trusted PHP code chooses adapters, tables, schema sources, and raw SQL. Authentication, authorization, row-level access, table/field allowlists, HTTP validation, page-size limits, and request-size limits belong to the application.

Schema-backed models reject undeclared fields, but schemas do not restrict trusted PHP code from opening undeclared dynamic tables. Authentication, authorization, and model/table selection still belong to the application. Reject unsupported filter features; do not replace failed filters with an empty `where`. Unknown operators and malformed options raise exceptions. Defaults bound filter depth, visited nodes, and membership-list size.

Update/delete accept only nonempty scalar equality filters. They do not accept recursive read predicates. Raw SQL and `reset()` are privileged operations; never expose them to arbitrary client input. `reset()` is a destructive MySQL-specific development helper, not a migration engine.

PDO read filters use connection-quoted values through a one-pass formatter; PDO writes bind parameters. WordPress uses wpdb's preparation/write APIs. Never interpolate untrusted fragments yourself. SQL errors can contain implementation details; translate exceptions into safe public responses.

Direct writes bypass WordPress APIs, hooks, and cache invalidation. Query events can contain sensitive values; redact and restrict logs. Event listeners are observational, not authorization hooks or a guaranteed transactional outbox.

## Reporting a vulnerability

Use GitHub's private vulnerability reporting on this repository when available. Do not post live credentials, customer data, or exploit details in a public issue. If private reporting is unavailable, request a private reporting channel without publishing sensitive details.
