# 0032 – Separate development database

Status: accepted (2026-10-28)

## Context
ADR 0029 recorded that there was no dev DB and that the development environment connected to the production one, which forced a read-only approach to manual checks and made `dev-login` risky.

## Decision
- The owner created a separate database and user for development. Each clone has its own git-ignored `.env`: `/workspace/hesk-dev/.env` → DB `hesk-dev`, user `hesk-dev`, `APP_ENV=dev`; `/workspace/hesk/.env` → DB `hesk`, user `hesk`, `APP_ENV=prod`.
- Schema commands, migrations and `dev-login` may be used in `hesk-dev`. The production clone `hesk` is only used for deployment, and its migrations are run by the owner (checklist in ADR 0029).
- Before any DB write, verify which database `.env` points to (read only `DB_DATABASE`; never print secrets).
- URLs: production https://hesk.krencl.cz/ (clone `hesk`), development https://hesk-dev.krencl.cz/ (clone `hesk-dev`).
- Dev is visually distinguishable: `GET /api/health` also returns `env` (the Symfony `APP_ENV`), and the frontend `Logo` component (header and login page) uses the green `logo-dev.svg` when `env` is `dev`, otherwise the blue `logo.svg`. `logo-dev.svg` is `logo.svg` with the hue of the blue fills (`#277bda`, `#699cd6`) recoloured to a darker green (see the file for the exact values; hue ~133°, saturation 62 %, lightness 17 points lower) – regenerate it (same recolouring of those two fills) when `logo.svg` changes.
- The same applies to the favicon and the app icon: `favicon-dev.png` and `icon-dev.png` are recoloured copies of `favicon.png` / `icon.png` (blue pixels: hue → ~133°, saturation → 62 %, lightness −17 points, weighted by how blue the pixel is so that anti-aliased edges blend). On `env = dev` the `Logo` component rewrites the `<link rel="icon">`/`apple-touch-icon` hrefs to the `-dev.png` files. Regenerate them whenever the originals change.

## Consequences
- Migrations can be tested on the dev DB before a release.
- The dev DB is not synchronised with production; its schema/data may lag behind or differ.
