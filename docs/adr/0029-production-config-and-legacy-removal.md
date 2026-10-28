# 0029 – Production configuration and removal of the old-application import

Status: accepted (2026-10-24)

## Context
The application runs in production at https://hesk.krencl.cz/, the old application and its directories have been removed and the data has been migrated. The import (ADR 0007, 0012) and the related code no longer have any use.

## Decision
- **Import removed:** `app:legacy:import`, `app:legacy:reset`, `src/Legacy/*`, `LegacyImportTest` (and with it `TestDatabase`, `SqliteNoFsyncMiddleware`), `ResetLegacyCommandTest`, the `LEGACY_DB_*` variables, the `schema_filter` for `legacy_*` and the backup of old covers (`/legacy-covers/`). It can be restored from git: the last commit containing them is `a4d9414` (`git checkout a4d9414 -- backend/src/Legacy backend/src/Command …`). ADR 0007 and 0012 remain as history.
- **Production configuration:** `backend/.env.prod` (committed, no secrets) sets `DEFAULT_URI`, `CORS_ALLOW_ORIGIN` only to the own domain, and `FRONTEND_URL`. Secrets go into `backend/.env.prod.local` (git-ignored, `chmod 600`; template `.env.prod.local.example`) or into environment variables. `APP_ENV=prod` is set in the process environment (php-fpm), or via `composer dump-env prod`; `.env` leaves it at `dev`.
- `dev-login` is disabled outside `dev`/`test` (404).

- **No dev DB:** the database that the development environment connects to (`/workspace/.env`) is the production one. Destructive operations and migrations are run only deliberately and after a backup (see `CLAUDE.md`).

## Deployment checklist
- `APP_ENV=prod`, own `APP_SECRET`, `composer install --no-dev -o --classmap-authoritative`, `php bin/console cache:clear` and `doctrine:migrations:migrate --no-interaction`.
- `npm run build` (output `web/static/`), nginx per ADR 0006 plus `location /uploads/ { }` (game images) – the `web/uploads/` folder must be writable by PHP and backed up.
- Google Console: redirect URI `https://hesk.krencl.cz/oauth/google/check`; nginx sends `X-Forwarded-Proto/Host/Port`.
- Game administrator: `php bin/console app:user:role <e-mail> ROLE_GAMES_ADMIN`.
- Backups of the DB and `web/uploads/`.
