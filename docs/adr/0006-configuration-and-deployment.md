# 0006 – Konfigurace, databáze a nasazení

Stav: přijato (2026-10-02)

## Kontext
Vývojová/ostrá DB je **MariaDB 11.0** (`mariadb:3306`, db `hesk`), ne MySQL, a už obsahuje data a schéma prototypu `hesk-dev-old` (tabulky `game`, `round`, `round_player`, `party`, `user`, `player`, `tag`, … ~540 odehraných kol). Konfigurace žije v souboru `/workspace/.env` (mimo git) s proměnnými `DB_HOSTNAME, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, OAUTH_GOOGLE_ID, OAUTH_GOOGLE_SECRET`.

## Rozhodnutí
- Doctrine DBAL se konfiguruje z jednotlivých `DB_*` proměnných (ne `DATABASE_URL`, kvůli speciálním znakům v hesle); `server_version: mariadb-11.0.2` (`DB_SERVER_VERSION`).
- `backend/.env` obsahuje jen prázdné/vývojové defaulty. Skutečné hodnoty se načtou z `backend/.env.local`, což je v dev **symlink na `../.env`** (git-ignorováno; na serveru stačí položit soubor). Symfony `.env.local` v prostředí `test` nečte, takže testy nikdy nesahají na skutečnou DB (SQLite, ADR 0003).
- Google OAuth proměnné se jmenují `OAUTH_GOOGLE_ID/SECRET` (shodně s prototypem). Redirect URI je **`/oauth/google/check`** (stejná jako u prototypu, takže v Google Console není nutná změna; alias `/api/auth/google/callback` zůstává, ale povolená není). `web/index.php` směruje `/oauth/*` do Symfony, Vite proxy také. Za reverzní proxy musí Symfony vidět `https` a správnou doménu, jinak se odešle jiná redirect URI → `redirect_uri_mismatch`: proto `TRUSTED_PROXIES` (default `127.0.0.1,REMOTE_ADDR`) a nginx musí posílat `X-Forwarded-Proto/Host/Port`. `FRONTEND_URL` je výchozí `/` (po přihlášení přesměrování na stejný origin).
- **Struktura kořene projektu:** `web/index.php` (front controller, v kořeni vedle `backend/` a `frontend/`) a `web/static/` (statické soubory uvnitř `web/` – výstup `npm run build`, git-ignorováno). `web/index.php` nastaví `project_dir` Symfony Runtime na `backend/` a načte `backend/vendor/autoload_runtime.php`; cache a logy zůstávají v `backend/var/`. Backend obsluhuje jen `/api/*`, ostatní cesty patří SPA (`web/static/index.html`).
- **`web/index.php` je hlavní rozcestník** (`.route.php` z prototypu se nepoužívá): `/api/*` → Symfony, ostatní cesty → `web/static/index.html` (SPA fallback); při běhu pod vestavěným PHP serverem navíc vrací existující soubory pod `/static/` jako statiku. Dev: `php -S 127.0.0.1:8000 -t web web/index.php` z kořene. Pro vývoj frontendu stačí `npm run dev` ve `frontend/` (Vite proxy `/api` → :8000). V produkci statiku servíruje web server.
- Ukázka nginx (document root = `web/`; build odkazuje na assety jako `/static/assets/…` díky `base: '/static/'` ve `vite.config.ts`):

```nginx
root /var/www/hesk/web;
location /static/ { }
location /api/ { fastcgi_pass php; include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME /var/www/hesk/web/index.php; }
location / { try_files /static/index.html =404; }
```
- Produkce: `APP_ENV=prod`, vlastní `APP_SECRET`, `composer install --no-dev -o`, `TRUSTED_PROXIES` za reverzní proxy (dle potřeby).

## Důsledky / otevřené
- Kolize názvů tabulek se stávajícím schématem byla vyřešena přejmenováním na `legacy_*` a importem dat (ADR 0007).
- Pozor na legacy schéma: `utf8mb3_czech_ci`, `datetime` s defaultem `0000-00-00`.
