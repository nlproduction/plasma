# Plasma development

Plasma is an independent MIT-licensed PHP ORM by the developers of MapSVG (https://mapsvg.com). Keep application-specific business logic, constants, UI metadata, and credentials out of this repository.

## Boundaries

- Public namespace: `Plasma\`. Keep PHP 7.4-compatible syntax in `src/`.
- The public API is documented in README and docs; internal SQL helpers are not a stable API.
- PHP arrays and JSON files are equivalent runtime schema sources; keep schema loading self-contained and deterministic.
- Query escaping is not authorization. Consumers own table/field allowlists and access control.
- Do not silently drop unsupported filters or swallow database errors.
- Keep the equality-only mutation contract explicit; do not accidentally apply read ASTs to writes.

## Verification

Run PHP lint, Composer validation, Pest, and the executable SQLite example. Add real database regressions for changes to prefixes, escaping, joins, or transaction accounting. Mock-only assertions are insufficient.

Use only disposable test databases. Keep changes focused; do not deploy consumers or alter production databases as part of package development. Run lengthy checks with captured logs and inspect their exit statuses before claiming success.

## Public presentation

Credit the developers of MapSVG and link https://mapsvg.com. License: MIT. Avoid references to private projects, credentials, or unpublished integrations in public docs. Describe the implemented Prisma-inspired subset, not full Prisma compatibility. Keep installation commands usable without assuming a package registry listing.
