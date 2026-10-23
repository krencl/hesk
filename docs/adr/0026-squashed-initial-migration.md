# 0026 – Jedna úvodní migrace místo historie

Stav: přijato (2026-10-23)

## Kontext
Za dobu vývoje vzniklo 19 migrací (přejmenování legacy tabulek, jednorázový import dat, průběžné změny schématu). Produkční data zatím neexistují a data ze staré aplikace se přenášejí příkazem `app:legacy:import` (ADR 0012), takže historie migrací nemá co chránit a jen zpomaluje založení databáze. Živá dev DB navíc po průběžných migracích nesouhlasila s entitami (přebytečný index na `play`).

## Rozhodnutí
- Všechny dosavadní migrace nahrazuje jediná `Version20261023000000` s čistým schématem vygenerovaným z entit (`doctrine:schema:create --dump-sql`). Nepřejmenovává legacy tabulky ani nepřenáší data – to dělá `app:legacy:import` (nahrazuje i migraci `…000200` z ADR 0007).
- Existující databáze se nepřevádí: smaže se (`doctrine:schema:drop --full-database --force`), znovu se spustí `doctrine:migrations:migrate` a `app:legacy:import`.
- **Od prvního nasazení do produkce se migrace už neslučují** – změny entit jdou do nových migrací (`doctrine:migrations:diff`).

## Důsledky
- Nová databáze se založí jedinou migrací a `doctrine:schema:validate` je v souladu s mapováním.
- Tabulky `legacy_*` (ADR 0007) už neexistují; zdrojem dat je přímo stará DB přes `LEGACY_DB_*`.
- Role (`ROLE_GAMES_ADMIN`) a obálky her nejsou ve staré DB, po smazání databáze se musí vrátit (`app:user:role`, `image_url`).
