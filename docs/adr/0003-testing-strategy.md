# 0003 – Strategie testování

Stav: přijato (2026-10-02); funkční testy API nahrazeny unit testy s mocky, viz [0022](0022-backend-tests-without-database.md)

## Rozhodnutí
- **Backend – unit testy** (`backend/tests/Unit`): čistá doménová logika bez DB – `ResultCalculator`, `Party` (invarianty adminů), `GameSession` (24h okno), `Game` (synchronizace rozšíření), `SessionAssigner` (repozitář jako stub).
- **Backend – funkční testy API** (`backend/tests/Functional`): celé HTTP toky přes `WebTestCase` nad **SQLite** (`.env.test`), schéma se vytváří `SchemaTool`. Důvod: rychlé, bez serveru. Pokrývá auth, oprávnění, vytvoření zápisu, seance, statistiky.
- **Frontend** (`frontend/src/**/*.test.ts(x)`): Vitest + jsdom. Čistá logika v `src/lib` (formulář zápisu, seskupení seancí, aktivní parta) a komponenty/stránky s mockovaným `fetch`.
- Spuštění: `cd backend && php bin/phpunit`, `cd frontend && npm test`.

## Důsledky / limity
- Funkční testy neběží proti MySQL, takže rozdíly platforem (collation, striktní režim, DQL specifika) nezachytí. **Schéma/migrace pro MySQL nebyly proti živému serveru ověřeny** – `Version20261002000000` je vygenerována z `doctrine:schema:create --dump-sql` pro platformu MySQL 8.4. Před prvním nasazením spustit migrace na reálném MySQL a ideálně přidat CI job s MySQL službou.
- Žádné E2E testy v prohlížeči (zatím).
