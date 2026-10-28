# 0026 – A single initial migration instead of a history

Status: accepted (2026-10-23)

## Context
During development 19 migrations accumulated (renaming of legacy tables, a one-off data import, incremental schema changes). There is no production data yet and the data from the old application is transferred by the `app:legacy:import` command (ADR 0012), so the migration history has nothing to protect and only slows down database creation. Moreover, the live dev DB, after the incremental migrations, did not match the entities (a redundant index on `play`).

## Decision
- All existing migrations are replaced by a single `Version20261023000000` with a clean schema generated from the entities (`doctrine:schema:create --dump-sql`). It does not rename legacy tables or transfer data – `app:legacy:import` does that (it also replaces migration `…000200` from ADR 0007).
- An existing database is not converted: it is dropped (`doctrine:schema:drop --full-database --force`), then `doctrine:migrations:migrate` and `app:legacy:import` are run again.
- **From the first production deployment on, migrations are no longer squashed** – entity changes go into new migrations (`doctrine:migrations:diff`).

## Consequences
- A new database is created by a single migration and `doctrine:schema:validate` is consistent with the mapping.
- The `legacy_*` tables (ADR 0007) no longer exist; the data source is the old DB directly via `LEGACY_DB_*`.
- Roles (`ROLE_GAMES_ADMIN`) and game covers are not in the old DB, so after wiping the database they must be restored (`app:user:role`, `image_url`).
