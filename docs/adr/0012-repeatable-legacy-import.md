# ADR 0012: Opakovatelný import ze staré aplikace

Stav: přijato (2026-10-16)

## Kontext
První import (ADR 0007) byl jednorázová migrace nad `legacy_*` kopiemi tabulek. Stará aplikace se ale dál používá a její databáze (`hesk-old`) se mění (v den zavedení: 731 zápisů místo 544, 108 her místo 81). Potřebujeme data do nové databáze (`hesk`) opakovaně přenášet, než se přepne.

## Rozhodnutí
- **Příkaz** `php bin/console app:legacy:import [--dry-run] [-v]` (`ImportLegacyCommand` + `App\Legacy\LegacyImporter`). Doporučeno spouštět s `--no-debug` (profiler si jinak pamatuje všechny dotazy) a nejdřív s `--dry-run`; `-v` vypíše změněné hodnoty.
- **Přístupy** ke staré DB jsou jen v proměnných prostředí `LEGACY_DB_HOSTNAME`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD`. Skutečné hodnoty patří do `backend/.env.dev.local` (git-ignorováno); commitnutý `backend/.env` má jen prázdné klíče. Spojení se starou DB se otevírá `SET SESSION TRANSACTION READ ONLY` a odmítne se, pokud je zdroj shodný s cílem.
- **Synchronizace, ne nahrávání od nuly.** Tabulka `legacy_map (entity, legacy_key, new_id)` drží vazbu „staré → nové“ (založí se sama; název `legacy_*` ignoruje `schema_filter`). Při dalším běhu se položky podle vazby aktualizují, nové se vloží a zápisy/výsledky smazané ve staré aplikaci se smažou. Seznam her, hráčů, tagů, rozšíření, postav se nemaže (jsou sdílené a mohou mít vazby).
- **Převzetí (adopce):** chybí-li vazba (první běh po jednorázové migraci, nebo ztráta tabulky), převezmou se existující řádky podle přirozených klíčů: e-mail (uživatel), id + název (parta), název v partě (hráč, hra, tag), název ve hře (rozšíření, postava), (parta, číslo) (seance), id + parta (zápis), (zápis, hráč) (výsledek). Nic se nezdvojí.
- **Co import vlastní:** názvy, typy bodování, zkrácené názvy, cheatsheety, vlastní hráč hry, rozšíření a postavy, seance (číslo a hranice), zápisy (čas v Europe/Prague → UTC, poznámka, rozšíření, tagy) a výsledky (skóre, pořadí, vítěz, začínající, postava).
- **Co import nepřepisuje:** přezdívku a Google účet existujícího uživatele (jen doplní chybějící), datum vzniku existujících řádků (ve staré DB bývá po obnově „dnes“), jména hráčů propojených s účtem, popis hry, a vše, co vzniklo jen v nové aplikaci (řádky bez vazby). Nové zápisy z nové aplikace nekolidují: staré id zápisu se použije jen, je-li volné.
- **Obálky her:** import je nekopíruje a `image_url` nepřepisuje; obrázky se nahrávají ve správě her (ADR 0020, původní kopírování obálek do `/static/covers/` zaniklo).
- Celý běh je jedna transakce; `--dry-run` ji vrátí zpět a vypíše, co by se změnilo.
- **Úplný reset:** `php bin/console app:legacy:reset [--yes]` (`ResetLegacyCommand`, jen `dev`) zkusí import nasucho (ověří přístup, než se cokoli smaže), zazálohuje role a obálky her (`var/legacy-reset-backup.json`; stará DB je nemá), smaže databázi, spustí migrace a import a role s obálkami vrátí (hry podle `bgg_id`, jinak názvu). Každý krok běží na čistém spojení – DDL v MariaDB potvrzuje transakce implicitně a import by jinak v témž procesu skončil chybou „There is no active transaction“.

## Důsledky
- Dokud se používá stará aplikace, jsou zápisy v ní zdrojem pravdy: úpravy importovaných zápisů a výsledků v nové aplikaci se při dalším importu přepíšou. Po přepnutí se příkaz přestane spouštět (a tabulku `legacy_map` i `legacy_*` lze smazat).
- Hry a hráči se zakládají jen v partách, kde se hrálo (jako dosud); zápis smazaný ve staré aplikaci smaže i seanci, která tím osiřela.
- Příkaz je pokrytý funkčními testy (`LegacyImportTest`): první import, opakování bez změn, aktualizace a mazání, převzetí po ztrátě vazeb, zkouška, kolize id a zachování účtů.
