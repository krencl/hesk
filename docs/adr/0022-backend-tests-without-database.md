# 0022 – Backendové testy bez databáze (mocky místo SQLite)

Stav: přijato (2026-10-22)

## Kontext
Funkční testy API (`ApiFlowTest`, `BggFlowTest`) procházely celé HTTP toky nad SQLite a pro každý test znovu vytvářely schéma. Na pomalém disku (fsync při každém commitu) běh trval přes 5 minut a testy závisely na stavu databáze.

## Rozhodnutí
- Testy backendu nesahají do databáze. Kontrolery, služby a repozitáře se testují jako unit testy (`tests/Unit/Controller`, `tests/Unit/Service`, …); `EntityManager`, repozitáře a dotazy jsou mocky/stuby (`tests/Support/DoctrineMocks`), entity žijí v paměti (`tests/Support/Fixtures` doplní `id`, která by přidělila DB).
- Kontrolery se volají přímo přes `tests/Support/ControllerHarness`: přihlášený uživatel, skutečný `PartyVoter` a vyhodnocení `#[IsGranted]` nad akcí (to by jinak udělal kernel), takže se ověřují i oprávnění (403).
- Validace vstupů (`tests/Unit/Dto/RequestValidationTest`), převod výjimek na JSON (`ApiExceptionListenerTest`) a přístupová pravidla (`ApiAccessTest`) mají vlastní testy bez kernelu.
- (Do 2026-10-24, ADR 0029) Jediná výjimka byl `tests/Functional/LegacyImportTest`: import je SQL z cizí databáze do schématu aplikace a mockem by se neověřil. Běží nad SQLite, schéma se vytvoří jednou za třídu (`TestDatabase`), v testovacím prostředí se vypíná fsync (`SqliteNoFsyncMiddleware`).
- Třídy zůstávají `final`; mockovat je umožňuje `dg/bypass-finals` (dev závislost, PHPUnit extension v `phpunit.dist.xml`).

## Důsledky / limity
- Běh celé sady ~1 s místo minut.
- Netestuje se, co je jen v SQL/DQL: řazení a hledání her (escapování `LIKE`), agregace statistik, `GameRepository::statsFor`, `PlayRepository`, unikátní klíče, kaskády, mapování entit a migrace. Tyto vrstvy teď nemají automatický test; ověřit je lze ručně proti dev DB, případně později malou sadou repozitářových testů s SQLite/MariaDB (viz limity v ADR 0003).
- Deserializace požadavků (`#[MapRequestPayload]`, neplatný enum, neplatný JSON) se v unit testech neprochází.
