# 0031 – Moje statistiky

Stav: přijato (2026-10-27)

## Kontext
Statistiky party (ADR 0024) ukazují celou partu. Hráč chce vidět sám sebe: v kterých hrách vyhrává nejvíc a jaké druhy her mu jdou.

## Rozhodnutí
- Stránka `/my-stats` (položka „Moje statistiky“ v menu pod „Seznam her“) ukazuje statistiky přihlášeného člena v **aktivní partě**: souhrn výher, 10 her s nejvíc výhrami jako sloupce (sloupec = výhry, plná šířka = všechny odehrané zápisy hry, text „výhry/zápisy“, název je odkaz na detail hry) a sloupcový graf výher podle **tagů her** (ADR 0024; hra s víc tagy se počítá do každého).
- API: `GET /api/parties/{id}/stats/me` (člen party) → `{player, plays, wins, topGames[{gameId,name,shortName,wins,plays}], gameTags[{name,wins,plays}]}`. Hráč je `Party::findPlayerFor(user)`; dotazy `PlayRepository::topWinningGamesOfPlayer` a `winsOfPlayerByGameTag` zahrnují jen hry/tagy s aspoň jednou výhrou.
- Další statistiky se budou přidávat na tutéž stránku.
