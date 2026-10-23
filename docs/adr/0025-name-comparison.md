# 0025 – Porovnávání jmen: `NameKey` podle kolace databáze

Stav: přijato (2026-10-23)

## Kontext
Jména hráčů party, tagů her a tagů zápisů mají v databázi unikátní klíč (`player`, `play_tag`: party + jméno, `game_tag`: jméno) a sloupce mají kolaci `utf8mb4_general_ci`, která nerozlišuje velikost písmen **ani diakritiku** („Čeněk“ = „cenek“). Kód ale jména porovnával jen bez ohledu na velikost písmen (`strcasecmp`, `mb_strtolower`), takže „Šimon“ vedle „Simon“ prošel doménovou kontrolou a teprve DB ho odmítla výjimkou – klient dostal HTTP 500.

## Rozhodnutí
- `App\Domain\NameKey::of()` vrací klíč jména (bez okolních mezer, bez diakritiky, malými písmeny; `symfony/string`). **Každé** porovnání a slučování jmen (hráči party, hráči hry, rozšíření, postavy, tagy) jde přes něj.
- `App\Domain\NamedItems` drží společný úklid seznamů pojmenovaných položek (`normalize`, `uniqueNames`) a párování existujících entit podle klíče (`resolve`); `Party::isPlayerNameTaken()` je jediná kontrola obsazenosti jména hráče.
- Hledání/zakládání tagů je v `GameTagRepository::findOrCreate()` a `PlayTagRepository::findOrCreate()` (jeden dotaz `WHERE name IN (…)`, shoda podle klíče, výsledek bez duplicit).
- Pojistka: `UniqueConstraintViolationException` pod `/api` vrací `ApiExceptionListener` jako HTTP 409 `{error: "Záznam se stejným názvem už existuje."}` místo 500 (kdyby kolace DB spojila i jména, která `NameKey` rozlišuje).

## Důsledky
- `NameKey` je striktnější než DB: transliteruje i jiná písma (např. „Иван“ = „ivan“) a „ß“ = „ss“, takže takové dvojice kód odmítne, i když by je DB snesla. Pro jména v partě to nevadí; opačný směr (DB spojí víc než `NameKey`) pokrývá 409.
- Porovnání v PHP je O(počet hráčů party); u malých part (jednotky až desítky hráčů) zanedbatelné.
