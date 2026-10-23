# ADR 0024: Tagy her a filtr statistik

Stav: přijato (2026-10-23)

## Kontext
Stará aplikace měla dva druhy tagů: tagy zápisů (`tag.type = round`, např. místo a příležitost; v nové aplikaci `Tag`, ADR 0004) a tagy her (`type = game`, tabulka `tag_game`: „Rychlovka“, „Strategie“, „Karetní“, …). Import tagy her zahazoval a statistiky se nedaly omezit. Stará aplikace uměla statistiky filtrovat podle tagů her (`gameTag`, `gameTagNot`).

## Rozhodnutí
- **Tagy her patří hře v katalogu** (ADR 0023), ne partě: entita `GameTag` (`name` unikátní bez ohledu na velikost písmen), vazba `Game::tags` (M:N, tabulka `game_game_tag`). Jsou oddělené od tagů zápisů (`Tag`, per parta), aby se nepletlo „Rychlovka“ s „Skalka“.
- **Úprava je sdílená data**, takže je mění jen správce her (`PUT /api/games/{id}`, pole `tags`) a při zakládání hry ten, kdo ji zakládá (`POST /api/parties/{id}/games`). Ve formuláři hry je pole „Tagy“ v zamykatelné části. Odpověď API nese `tags` (seřazené, bez ohledu na velikost písmen); neexistující tagy se při uložení založí.
- **Seznamy tagů:** `GET /api/game-tags` (všechny v katalogu, našeptávání ve formuláři) a `GET /api/parties/{id}/game-tags` (jen tagy her zařazených v partě, nabídka filtru statistik).
- **Filtr statistik:** `GET /api/parties/{id}/stats?games[]=<id>&tags[]=…&excludeGames[]=<id>&excludeTags[]=…` (`StatsFilter`). Zápis projde, když hra patří mezi vybrané hry **nebo** má **aspoň jeden** z vybraných tagů (`games`, `tags`; bez výběru projde vše) **a zároveň** hra není mezi vyřazenými hrami ani nemá **žádný** z vyřazených tagů (`excludeGames`, `excludeTags`; vyřazení má přednost). Platí pro výhry hráčů i nejhranější hry (`PlayRepository::winsPerPlayer/topGames`, poddotazy `EXISTS` s unikátními aliasy). Na rozdíl od staré aplikace se výběr her a tagů nespojuje „a zároveň“, ale „nebo“, protože uživatel je vybírá jedním polem.
- **UI statistik:** jedno vyhledávací pole ve stejném vzhledu jako seznam her („Hra nebo tag“) hledá ve hrách party (server, `GET /games?q=`) a v tazích her party (`GET /parties/{id}/game-tags`, bez ohledu na diakritiku a velikost písmen); výsledky nahradí grafy. Vybraná hra/tag se přidá jako štítek „+“ (jen takové hry), klepnutí na štítek ho změní na „−“ (bez nich), křížek ho odebere. Filtr se zobrazí, jen když parta nějakou hru odehrála; prázdná parta ukáže jen větu „Ještě jste nic neodehráli, je čas to napravit.“ a filtr, který nic nenajde, hlášku „Žádná odehraná hra neodpovídá filtru.“
- **Import** (ADR 0012) přenáší `tag_game` ke hrám v katalogu (převzetí podle názvu, klíč vazby `gametag` = staré id tagu); tagy se jen přidávají, ruční změny v nové aplikaci neodebírá.
- Migrace `Version20261028000000`.

## Důsledky
- Změna tagů hry se projeví ve všech partách; člen party tagy sdílené hry nemění (jen správce her).
- Filtr stavu není v URL ani se neukládá; po opuštění stránky se vynuluje.
- Tagy zápisů se do filtru statistik zatím nepromítají (stará aplikace to uměla – `roundTag`); lze doplnit stejným způsobem.
