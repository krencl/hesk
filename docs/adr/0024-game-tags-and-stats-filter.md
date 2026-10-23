# ADR 0024: Tagy her a filtr statistik

Stav: přijato (2026-10-23)

## Kontext
Stará aplikace měla dva druhy tagů: tagy zápisů (`tag.type = round`, např. místo a příležitost; v nové aplikaci `Tag`, ADR 0004) a tagy her (`type = game`, tabulka `tag_game`: „Rychlovka“, „Strategie“, „Karetní“, …). Import tagy her zahazoval a statistiky se nedaly omezit. Stará aplikace uměla statistiky filtrovat podle tagů her (`gameTag`, `gameTagNot`).

## Rozhodnutí
- **Tagy her patří hře v katalogu** (ADR 0023), ne partě: entita `GameTag` (`name` unikátní bez ohledu na velikost písmen), vazba `Game::tags` (M:N, tabulka `game_game_tag`). Jsou oddělené od tagů zápisů (`Tag`, per parta), aby se nepletlo „Rychlovka“ s „Skalka“.
- **Úprava je sdílená data**, takže je mění jen správce her (`PUT /api/games/{id}`, pole `tags`) a při zakládání hry ten, kdo ji zakládá (`POST /api/parties/{id}/games`). Ve formuláři hry je pole „Tagy“ v zamykatelné části. Odpověď API nese `tags` (seřazené, bez ohledu na velikost písmen); neexistující tagy se při uložení založí.
- **Seznamy tagů:** `GET /api/game-tags` (všechny v katalogu, našeptávání ve formuláři) a `GET /api/parties/{id}/game-tags` (jen tagy her zařazených v partě, nabídka filtru statistik).
- **Filtr statistik:** `GET /api/parties/{id}/stats?tags[]=…&excludeTags[]=…` – `tags` nechá jen zápisy her s **aspoň jedním** z tagů, `excludeTags` vyřadí hry s **kterýmkoli** z nich (stejně jako stará aplikace); platí pro výhry hráčů i nejhranější hry (`PlayRepository::winsPerPlayer/topGames`, poddotazy `EXISTS`).
- **UI statistik:** nad grafy jsou tagy jako štítky; klepnutí přepíná „bez filtru → jen s tagem (+) → bez tagu (−)“. Filtr se zobrazí, jen když parta nějakou hru odehrála; prázdná parta ukáže jen větu „Ještě jste nic neodehráli, je čas to napravit.“ a filtr, který nic nenajde, hlášku „Žádná odehraná hra neodpovídá filtru.“
- **Import** (ADR 0012) přenáší `tag_game` ke hrám v katalogu (převzetí podle názvu, klíč vazby `gametag` = staré id tagu); tagy se jen přidávají, ruční změny v nové aplikaci neodebírá.
- Migrace `Version20261028000000`.

## Důsledky
- Změna tagů hry se projeví ve všech partách; člen party tagy sdílené hry nemění (jen správce her).
- Filtr stavu není v URL ani se neukládá; po opuštění stránky se vynuluje.
- Tagy zápisů se do filtru statistik zatím nepromítají (stará aplikace to uměla – `roundTag`); lze doplnit stejným způsobem.
