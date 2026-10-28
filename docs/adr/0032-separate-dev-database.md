# 0032 – Separate development database

Status: accepted (2026-10-28)

## Context
ADR 0029 recorded that there was no dev DB and that the development environment connected to the production one, which forced a read-only approach to manual checks and made `dev-login` risky.

## Decision
- The owner created a separate database and user for development. Each clone has its own git-ignored `.env`: `/workspace/hesk-dev/.env` → DB `hesk-dev`, user `hesk-dev`, `APP_ENV=dev`; `/workspace/hesk/.env` → DB `hesk`, user `hesk`, `APP_ENV=prod`.
- Schema commands, migrations and `dev-login` may be used in `hesk-dev`. The production clone `hesk` is only used for deployment, and its migrations are run by the owner (checklist in ADR 0029).
- Before any DB write, verify which database `.env` points to (read only `DB_DATABASE`; never print secrets).
- URLs: production https://hesk.krencl.cz/ (clone `hesk`), development https://hesk-dev.krencl.cz/ (clone `hesk-dev`).

## Consequences
- Migrations can be tested on the dev DB before a release.
- The dev DB is not synchronised with production; its schema/data may lag behind or differ.
