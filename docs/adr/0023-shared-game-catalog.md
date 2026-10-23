# ADR 0023: Sdílený katalog her

Stav: přijato (2026-10-22)

## Kontext
Hra patřila jedné partě (`game.party_id`), takže stejná hra (třeba Vládce Tokia) existovala v databázi tolikrát, kolik part ji hraje, a správce více part ji musel upravovat na několika místech. Ve staré aplikaci i na BoardGameGeek jsou hry jedna společná databáze. Cíl: jedna velká databáze her, o kterou se starají jen správci her, a parta si z ní hry jen „zařazuje“.

## Rozhodnutí
- **`game` je globální katalog** (bez `party_id`): název, zkratka, bodování, obálka, popis, cheatsheet, `bgg_id`, rozšíření a postavy jsou společné všem partám. `bgg_id` je **unikátní** (hra z BGG je v katalogu nejvýš jednou).
- **`party_game (party, game)`** (entita `PartyGame`, unikát `(party_id, game_id)`) je vazba hry na partu. Patří do ní i **vlastní hráči hry** (`party_game_player`, dřív `game_player`, ADR 0019), protože hráči jsou členy konkrétní party. `Party::addGame/removeGame/hasGame/findGameLink` nahrazují dotazy na `game.party`.
- **Zápisy, seance, tagy a statistiky zůstávají per parta**: `play` ukazuje na globální hru a partu, statistiky a poslední zápis hry se počítají jen z zápisů dané party (`GameRepository::statsFor(party, games)`). Zápis smí vzniknout jen u hry zařazené v partě.
- **Žádné duplicity.** Hledání při přidání hry nabízí nejdřív hry party („Už v partě“), potom ostatní hry katalogu („Z lokální databáze“), pak cache a BGG; stejné `bgg_id` se nenabízí dvakrát. **Výběr hry z katalogu ji rovnou zařadí do party** (`POST /api/parties/{id}/game-links {gameId}`, idempotentní), žádný formulář ani nová hra. Nová hra v katalogu vzniká jen z BGG (formulář s oříznutím obálky) nebo jako **vlastní hra bez `bgg_id`** (výjimka). `POST /api/parties/{id}/games` odmítne duplicitu (422): stejné `bgg_id`, u vlastní hry stejný název (bez ohledu na velikost písmen); totéž platí při úpravě (`GameRepository::findDuplicate`).
- **Oprávnění:** sdílená data hry smí upravovat (`PUT /api/games/{id}`) a mazat (`DELETE /api/games/{id}`, jen hru bez zápisů v jakékoli partě) jen **správce her** = uživatel s rolí `ROLE_GAMES_ADMIN` (`user.roles`, JSON; v `GET /api/me` jako `roles`). Roli přidělí `php bin/console app:user:role <e-mail> [ROLE] [--remove]`. Hry party spravuje **zapisovač a správce party** (`PARTY_WRITE`): založit hru (nová z BGG/vlastní), zařadit ji do party, odebrat z party (`DELETE /api/parties/{id}/games/{gameId}`, ne když v partě má zápisy; hra zůstává v katalogu), nahrát obálku a měnit vlastní hráče hry party (`PUT /api/parties/{id}/games/{gameId}/players`). Obyčejný člen hry jen čte (seznam, detail, vyhledávání), „Spravovat hry“ ani přidání hry nevidí (`RequireWriter`). Správa správců her (UI) je záměrně odložena.
- **Detail hry je v kontextu party:** `GET /api/parties/{id}/games/{gameId}` (404, není-li hra v partě); `GET /api/games/{id}` zrušeno.
- **Formulář hry** (`/games/:id/edit`): nesprávce vidí sdílená pole zamčená (`<fieldset disabled>`) a ukládá jen hráče hry; správce uloží sdílená data (`PUT /api/games/{id}`) a potom hráče. Dole je „Odebrat z party“ a (správce) „Smazat hru z databáze“.
- **Import ze staré aplikace** (ADR 0012) vytváří každou hru jednou (převzetí podle názvu bez ohledu na velikost písmen, klíče vazby `legacy_map` jsou prosté staré id her, rozšíření a postav) a zařazuje ji do part, kde se hrálo; vlastní hráči hry se přiřadí v těch partách.
- **Migrace** `Version20261027000000` převede stávající hry 1:1 (každá zůstane ve své partě, `game_player` → `party_game_player`) a nevratná je (`down` vyhodí výjimku). Sloučení stejných her z různých part řeší až nový import ze staré aplikace (před ním se data vymažou; staré hry měly duplicity jen díky starému modelu).

## Důsledky
- Úpravy hry se projeví ve všech partách; členové ostatních part je nemohou ovlivnit (jen správce her).
- Obálky ani cheatsheety už nejsou per parta. Hra smazaná z party je dál v katalogu (u hry bez party lze správcem smazat).
- Hry s týmž názvem a různým `bgg_id` (různé hry) v katalogu mohou být, hlídá se jen vlastní hra bez `bgg_id`.
- ADR 0021: hra z jiné party se už nepředvyplňuje do formuláře (`prefill` zrušen), odpověď hledání nese `gameId` a `imageUrl`.
