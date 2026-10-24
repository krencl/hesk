# 0029 – Produkční konfigurace a odstranění importu ze staré aplikace

Stav: přijato (2026-10-24)

## Kontext
Aplikace běží v produkci na https://hesk.krencl.cz/, stará aplikace i její adresáře byly odstraněny a data jsou převedená. Import (ADR 0007, 0012) a s ním související kód už nemá využití.

## Rozhodnutí
- **Import odstraněn:** `app:legacy:import`, `app:legacy:reset`, `src/Legacy/*`, `LegacyImportTest` (a s ním `TestDatabase`, `SqliteNoFsyncMiddleware`), `ResetLegacyCommandTest`, proměnné `LEGACY_DB_*`, `schema_filter` pro `legacy_*` a záloha starých obálek (`/legacy-covers/`). Obnovit jde z gitu: poslední commit, který je obsahuje, je `a4d9414` (`git checkout a4d9414 -- backend/src/Legacy backend/src/Command …`). ADR 0007 a 0012 zůstávají jako historie.
- **Produkční konfigurace:** `backend/.env.prod` (commitovaný, bez tajemství) nastavuje `DEFAULT_URI`, `CORS_ALLOW_ORIGIN` jen na vlastní doménu a `FRONTEND_URL`. Tajemství jdou do `backend/.env.prod.local` (git-ignorováno, `chmod 600`; šablona `.env.prod.local.example`) nebo do proměnných prostředí. `APP_ENV=prod` se nastavuje v prostředí procesu (php-fpm), případně `composer dump-env prod`; `.env` ho nechává na `dev`.
- `dev-login` je mimo `dev`/`test` vypnutý (404).

## Kontrolní seznam nasazení
- `APP_ENV=prod`, vlastní `APP_SECRET`, `composer install --no-dev -o --classmap-authoritative`, `php bin/console cache:clear` a `doctrine:migrations:migrate --no-interaction`.
- `npm run build` (výstup `web/static/`), nginx podle ADR 0006 a navíc `location /uploads/ { }` (obrázky her) – složka `web/uploads/` musí být zapisovatelná pro PHP a zálohovaná.
- Google Console: redirect URI `https://hesk.krencl.cz/oauth/google/check`; nginx posílá `X-Forwarded-Proto/Host/Port`.
- Správce her: `php bin/console app:user:role <e-mail> ROLE_GAMES_ADMIN`.
- Zálohy DB a `web/uploads/`.
