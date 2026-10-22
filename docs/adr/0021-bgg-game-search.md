# ADR 0021: Zakládání her přes BoardGameGeek

Stav: přijato (2026-10-21)

## Kontext
Nová hra byla prázdný formulář (název, bodování, obrázek, …). Hlavním zdrojem má být BoardGameGeek (BGG XML API2): uživatel zadá jen název, hra se najde a předvyplní se. Vlastní hru smí založit, jen když se nenajde.

BGG XML API2 **vyžaduje registrovanou aplikaci a token** (`Authorization: Bearer`, požadavky na `boardgamegeek.com` bez `www`); schválení aplikace na https://boardgamegeek.com/applications může trvat týden i déle. Oficiální stránku s podmínkami jsem z vývojového prostředí nemohl načíst (403), takže detaily (limity, licence, atribuce) je třeba ověřit při registraci; do UI je dán odkaz „Data her poskytuje BoardGameGeek“.

## Rozhodnutí
- **První krok nové hry je jen pole Název** (`BggGameSearch`, `/games/new`). Formulář hry (beze změny, jen s `bggId`) se ukáže po výběru výsledku, nebo po „Založit vlastní hru“; ta se nabídne až po dokončeném hledání (≥ 2 znaky, debounce) a předvyplní zadaný název. Úprava hry (`/games/:id/edit`) začíná rovnou formulářem.
- **Pořadí zdrojů** (`BggCatalog::search`, `GET /api/parties/{id}/game-search?q=`): 1) hry party (lokální DB; hra s `gameId` je jen odkaz „Už v partě“, aby nevznikaly duplicity), 2) hry z ostatních part (`source: catalog`, stejná tabulka `game`; jedna za název, preferuje se ta s `bgg_id` a obrázkem, a ne ta, jejíž název nebo `bgg_id` už party má; výsledek nenese `gameId` a nese `prefill` – zkratku, bodování a lokální obrázek –, takže výběr předvyplní formulář bez dotazu na server a v partě vznikne nová hra; popis, cheatsheet, rozšíření ani hráči se nepřebírají), 3) lokální cache BGG (`bgg_game`, sdílená mezi partami; jen název, rok a adresa obrázku), 4) živé BGG API. API se volá, jen když první tři zdroje dohromady dají méně než `LOCAL_ENOUGH` (5) výsledků; co vrátí, uloží se do cache. Duplicity se rozpoznávají podle `bggId`. Odpověď nese `bgg: used | skipped | unavailable`; při nedostupnosti (bez tokenu, chyba, 429) zůstanou lokální výsledky a UI to oznámí – vlastní hra jde založit vždy.
- **Výběr hry** (`POST /api/parties/{id}/bgg/import {bggId}`) vrací `{bggId, name, bggImageUrl}`; `bggImageUrl` je originál na BGG CDN. Je-li hra s adresou už v cache, BGG se nevolá. Popis, rozšíření apod. se nepřebírají (BGG popis je dlouhý anglický text); bodování zůstává výchozí „nejvíc bodů“ a dolaďuje se ve formuláři.
- **Obrázek:** server ho **neukládá**. Formulář ukáže živý náhled z CDN a hned otevře ořezový popup z ADR 0020; až **výřez** se nahraje stávajícím `POST …/game-images` a uloží lokálně jako WebP. Zrušený ořez nechá náhled a tlačítko „Oříznout obrázek z BGG“; neoříznutý obrázek se ke hře neuloží (`image_url` dál smí být jen `/uploads/covers/…`). Canvas nesmí číst obrázek z cizího originu bez CORS (a hlavičky CDN jsem neověřil), proto ořez načítá obrázek přes proxy `GET /api/bgg/image?url=` (jen přihlášení; jen `https` na `geekdo-images.com` a subdomény, bez přesměrování a portů, max. 10 MB, typ se určuje podle obsahu).
- **Model:** `game.bgg_id` (nullable, posílá ho formulář i při úpravě; odkaz na BGG ve formuláři) a tabulka `bgg_game` (`bgg_id` unikátní, `name`, `published_year`, `image_url` = CDN adresa, `fetched_at`). Cache zatím nemá expiraci.
- **Konfigurace:** `BGG_API_TOKEN` v `.env.local`/prostředí (prázdný = nenastaveno; potom se hledá jen v partě a v cache). `BggClient` parsuje XML přes `ext-dom` a používá `symfony/http-client`; v testech ho nahrazuje `FakeBggClient`, formát odpovědí BGG ověřuje `BggClientTest` na ukázkovém XML.

## Důsledky
- **Naostro nevyzkoušeno:** do schválení aplikace na BGG a vložení tokenu nešlo volat skutečné API (tvar XML je podle dokumentace/ukázek) ani ověřit adresu CDN obrázků; první ostrý běh je třeba zkontrolovat (hledání, `thing`, proxy obrázku, ořez).
- Cache bez expirace: změny názvů/obrázků na BGG se do ní promítnou jen při novém hledání (jméno, rok) nebo vůbec (adresa obrázku).
- Proxy obrázku je jediná cesta, kudy server stahuje cizí obsah; omezení hostu je v `BggClient::fetchImage`.
- Hry založené dřív nemají `bgg_id`; napojení na BGG se jim dá doplnit jen novým zakládáním (zatím bez „napárování“ existující hry).
- Hledání her z ostatních part prozrazuje členům party názvy (a zkratky, obrázky) her jiných part; jiné údaje se nevracejí.
- **Přejmenování cesty** (2026-10-22): hledání je `GET /api/parties/{id}/game-search` (původně `…/bgg/search`), protože primárním zdrojem je lokální databáze a BGG je až poslední krok. Pod `…/bgg/` zůstává jen převzetí hry z BGG (`POST …/bgg/import`) a proxy obrázků (`/api/bgg/image`).
- **Sdílený katalog** (ADR 0023, 2026-10-22): zdroj 2 jsou všechny hry katalogu, které party nemá; výsledek nese `gameId` a `imageUrl` (už ne `prefill`) a jeho výběr hru **rovnou zařadí do party** (`POST /api/parties/{id}/game-links`), nezakládá novou. U všech výsledků se vrací `imageUrl` (lokální nebo z BGG CDN z cache, u živého hledání bývá `null`).
