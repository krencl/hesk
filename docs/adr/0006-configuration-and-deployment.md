# 0006 – Configuration, Database and Deployment

Status: accepted (2026-10-02)

## Context
The development/live DB is **MariaDB 11.0** (`mariadb:3306`, db `hesk`), not MySQL, and it already contains the data and schema of the `hesk-dev-old` prototype (tables `game`, `round`, `round_player`, `party`, `user`, `player`, `tag`, … ~540 recorded rounds). Configuration lives in the file `/workspace/.env` (outside git) with the variables `DB_HOSTNAME, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, OAUTH_GOOGLE_ID, OAUTH_GOOGLE_SECRET`.

## Decision
- Doctrine DBAL is configured from the individual `DB_*` variables (not `DATABASE_URL`, because of special characters in the password); `server_version: mariadb-11.0.2` (`DB_SERVER_VERSION`).
- `backend/.env` contains only empty/development defaults. Real values are loaded from `backend/.env.local`, which in dev is a **symlink to `../.env`** (git-ignored; on a server it is enough to drop the file in place). Symfony does not read `.env.local` in the `test` environment, so tests never touch the real DB (SQLite, ADR 0003).
- Google OAuth variables are named `OAUTH_GOOGLE_ID/SECRET` (same as the prototype). The redirect URI is **`/oauth/google/check`** (the same as in the prototype, so no change is needed in Google Console; the alias `/api/auth/google/callback` remains, but is not allowed). `web/index.php` routes `/oauth/*` to Symfony, the Vite proxy does too. Behind a reverse proxy Symfony must see `https` and the correct domain, otherwise a different redirect URI is sent → `redirect_uri_mismatch`: hence `TRUSTED_PROXIES` (default `127.0.0.1,REMOTE_ADDR`) and nginx must send `X-Forwarded-Proto/Host/Port`. `FRONTEND_URL` defaults to `/` (after login a redirect to the same origin).
- **Project root structure:** `web/index.php` (front controller, in the root next to `backend/` and `frontend/`) and `web/static/` (static files inside `web/` – the output of `npm run build`, git-ignored). `web/index.php` sets the Symfony Runtime `project_dir` to `backend/` and loads `backend/vendor/autoload_runtime.php`; cache and logs stay in `backend/var/`. The backend serves only `/api/*`, other paths belong to the SPA (`web/static/index.html`).
- **`web/index.php` is the main router** (`.route.php` from the prototype is not used): `/api/*` → Symfony, other paths → `web/static/index.html` (SPA fallback); when running under the built-in PHP server it additionally returns existing files under `/static/` as static content. Dev: `php -S 127.0.0.1:8000 -t web web/index.php` from the root. For frontend development `npm run dev` in `frontend/` is enough (Vite proxy `/api` → :8000). In production the web server serves static files.
- Example nginx (document root = `web/`; the build references assets as `/static/assets/…` thanks to `base: '/static/'` in `vite.config.ts`):

```nginx
root /var/www/hesk/web;
location /static/ { }
location /api/ { fastcgi_pass php; include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME /var/www/hesk/web/index.php; }
location / { try_files /static/index.html =404; }
```
- Production: `APP_ENV=prod`, own `APP_SECRET`, `composer install --no-dev -o`, `TRUSTED_PROXIES` behind a reverse proxy (as needed).

## Consequences / open
- Table name collisions with the existing schema were resolved by renaming to `legacy_*` and importing the data (ADR 0007).
- Beware of the legacy schema: `utf8mb3_czech_ci`, `datetime` with a default of `0000-00-00`.
