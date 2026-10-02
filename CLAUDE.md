# CLAUDE.md – HESK (zápisy z deskových her)

Webová aplikace pro zapisování výsledků z hraní deskových her a statistiky skupiny hráčů. Komunikace s uživatelem **česky**. Všechny změny commitovat do gitu; architektonická rozhodnutí zapisovat do `docs/adr/` (viz `docs/README.md`).

## Struktura repa
- `backend/` – Symfony 7.4 LTS, PHP ≥ 8.4, Doctrine ORM (MySQL 8.4), PHPUnit. REST API pod `/api`.
- `frontend/` – React 19 + TypeScript + Vite, TanStack Query, React Router, Vitest.
- `docs/` – ADR + poznámky. `hesk-tmp/` (rozpracovaný prototyp, git-ignorováno) a `/hesk-original` (produkce, Node/RethinkDB) jsou **jen vzor**, ne součást nové app.

## Příkazy
- Backend: `cd backend && composer install && php bin/phpunit` · dev server `php -S 127.0.0.1:8000 -t public` · DB `docker compose up -d database` + `php bin/console doctrine:migrations:migrate`.
- Frontend: `cd frontend && npm install && npm test` · `npm run dev` (proxy `/api` → :8000) · `npm run build` (včetně `tsc`).
- Lokální konfigurace do `backend/.env.local` (`DATABASE_URL`, `GOOGLE_CLIENT_ID/SECRET`, `FRONTEND_URL`). Bez Google klíčů lze ve vývoji použít `POST /api/auth/dev-login` (login formulář ve Vite dev módu).

## Prostředí (tento remote kontejner)
Není tu PHP ani MySQL ani sudo. PHP 8.4 je statický binár v `~/bin/php` + `~/bin/composer` (`export PATH=$HOME/bin:$PATH`; nepřežije nový kontejner – znovu stáhnout z `dl.static-php.dev`, viz ADR 0001). MySQL tu neběží, proto testy používají SQLite; Chromium nelze spustit (chybí systémové knihovny).

## Backend – orientace
- `src/Entity` – `User, Party, PartyMember, PartyInvitation, Game, GameAddon, Place, Tag, GameSession, Play, PlayResult`.
- `src/Domain` – čistá logika: `ResultCalculator` (pořadí/vítězové dle `ScoreType`), `PlayerInput/Outcome`, `Exception/DomainException` (→ HTTP 422).
- `src/Service` – `PlayService` (create/update/delete zápisu), `SessionAssigner` (24h seance), `Presenter` (entity → JSON).
- `src/Controller` – tenké kontrolery (`Party, Game, Play, Stats, Auth, Health`); vstup přes `#[MapRequestPayload]` DTO v `src/Dto`.
- `src/Security` – `PartyVoter` (`PARTY_VIEW`/`PARTY_ADMIN`), `JsonEntryPoint` (401 JSON), `SessionLoginAuthenticator`.
- `src/EventListener/ApiExceptionListener` – chyby pod `/api` jako JSON `{error, violations?}`.
- `migrations/` – ručně složená z `doctrine:schema:create --dump-sql` (MySQL); po změně entit vygenerovat novou migraci proti reálné DB.
- Testy: `tests/Unit` (bez DB), `tests/Functional` (SQLite, `ApiFlowTest`).

## Frontend – orientace
- `src/api` (klient + typy + endpointy) · `src/lib` (čistá logika: `playForm`, `scoring`, `activeParty`, `afterLogin`, `datetime`) · `src/pages` · `src/components` · `src/AppContext.tsx` (me, parties, aktivní skupina) · `src/App.tsx` (routy + gating login → přezdívka → skupina).
- Mobile-first, max. šířka 480 px, UI v češtině.

## Konvence a pasti
- Pravidla domény patří do entit/`Domain`, ne do kontrolerů; kontrolery vrací `Presenter` pole.
- `list<DTO>` v DTO funguje jen díky `phpdocumentor/reflection-docblock` + `phpstan/phpdoc-parser` (bez nich se pole nedeserializují na objekty).
- Funkční testy mažou a vytvářejí schéma pro každý test (pomalé, ~35 s celkem).
- Taháček her je HTML od uživatelů → vždy přes `SafeHtml` (DOMPurify).
- Git: větev `master`, commity s trailerem `Co-Authored-By` dle zadání prostředí.

## Stav (2026-10-02)
Hotovo: auth (Google + dev login), skupiny/členové/pozvánky, hry (+přídavky), zápisy se seancemi, statistiky (výhry, top hry), mobilní UI.
Nehotovo/nápady: editace zápisu ve UI, vynucení přezdívky na backendu, upload obrázků, WYSIWYG taháček, CI s MySQL, import z původní app, postavy/„začínající hráč“.
