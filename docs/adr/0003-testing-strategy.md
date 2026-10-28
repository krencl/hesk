# 0003 – Testing Strategy

Status: accepted (2026-10-02); functional API tests replaced by unit tests with mocks, see [0022](0022-backend-tests-without-database.md)

## Decision
- **Backend – unit tests** (`backend/tests/Unit`): pure domain logic without a DB – `ResultCalculator`, `Party` (admin invariants), `GameSession` (24h window), `Game` (expansion synchronization), `SessionAssigner` (repository as a stub).
- **Backend – functional API tests** (`backend/tests/Functional`): whole HTTP flows via `WebTestCase` on **SQLite** (`.env.test`), the schema is created by `SchemaTool`. Reason: fast, no server needed. Covers auth, permissions, play creation, sessions, statistics.
- **Frontend** (`frontend/src/**/*.test.ts(x)`): Vitest + jsdom. Pure logic in `src/lib` (play form, session grouping, active party) and components/pages with a mocked `fetch`.
- Running: `cd backend && php bin/phpunit`, `cd frontend && npm test`.

## Consequences / limits
- Functional tests do not run against MySQL, so they do not catch platform differences (collation, strict mode, DQL specifics). **The schema/migrations for MySQL have not been verified against a live server** – `Version20261002000000` was generated from `doctrine:schema:create --dump-sql` for the MySQL 8.4 platform. Before the first deployment, run the migrations on a real MySQL and ideally add a CI job with a MySQL service.
- No E2E browser tests (yet).
