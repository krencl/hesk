# 0006 – Konfigurace, databáze a nasazení

Stav: přijato (2026-10-02)

## Kontext
Vývojová/ostrá DB je **MariaDB 11.0** (`mariadb:3306`, db `hesk`), ne MySQL, a už obsahuje data a schéma prototypu `hesk-tmp` (tabulky `game`, `round`, `round_player`, `party`, `user`, `player`, `tag`, … ~540 odehraných kol). Konfigurace žije v souboru `/workspace/.env` (mimo git) s proměnnými `DB_HOSTNAME, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, OAUTH_GOOGLE_ID, OAUTH_GOOGLE_SECRET`.

## Rozhodnutí
- Doctrine DBAL se konfiguruje z jednotlivých `DB_*` proměnných (ne `DATABASE_URL`, kvůli speciálním znakům v hesle); `server_version: mariadb-11.0.2` (`DB_SERVER_VERSION`).
- `backend/.env` obsahuje jen prázdné/vývojové defaulty. Skutečné hodnoty se načtou z `backend/.env.local`, což je v dev **symlink na `../.env`** (git-ignorováno; na serveru stačí položit soubor). Symfony `.env.local` v prostředí `test` nečte, takže testy nikdy nesahají na skutečnou DB (SQLite, ADR 0003).
- Google OAuth proměnné se jmenují `OAUTH_GOOGLE_ID/SECRET` (shodně s prototypem). Callback je `/api/auth/google/callback` → tuto **redirect URI je nutné povolit v Google Console** (prototyp používal `/oauth/google/check`).
- Produkce: `APP_ENV=prod`, vlastní `APP_SECRET`, `composer install --no-dev -o`, `TRUSTED_PROXIES` za reverzní proxy (dle potřeby).

## Důsledky / otevřené
- **Nový doménový model (ADR 0004) kolizně pojmenovává tabulky se stávajícím schématem** (`party`, `game`, `tag`, `user`…). Migrace `Version20261002000000` se proti této DB **nesmí spustit**, dokud není rozhodnuto: (a) přizpůsobit entity stávajícímu schématu, nebo (b) nové schéma do jiné DB/prefixu + jednorázový import dat.
- Pozor na legacy schéma: `utf8mb3_czech_ci`, `datetime` s defaultem `0000-00-00`, enumy (`highest_score|lowest_score|order|only_winner`), hráči jako samostatná entita `player` (i „herní“ hráči), `session_number` na kole.
