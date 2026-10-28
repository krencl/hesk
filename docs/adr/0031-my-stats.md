# 0031 – My statistics

Status: accepted (2026-10-27)

## Context
Party statistics (ADR 0024) show the whole party. A player wants to see themselves: in which games they win the most and which kinds of games suit them.

## Decision
- The page `/my-stats` (menu item "Moje statistiky" (My statistics) under "Seznam her" (Game list)) shows the statistics of the logged-in member in the **active party**: a summary of wins, the 10 games with the most wins as bars (bar = wins, full width = all played plays of the game, text "výhry/zápisy" (wins/plays) (the second value in the secondary color), the name is a link to the game detail) and a bar chart of wins by **game tags** (ADR 0024; a game with several tags counts toward each).
- API: `GET /api/parties/{id}/stats/me` (party member) → `{player, plays, wins, topGames[{gameId,name,shortName,wins,plays}], gameTags[{name,wins,plays}]}`. The player is `Party::findPlayerFor(user)`; the queries `PlayRepository::topWinningGamesOfPlayer` and `winsOfPlayerByGameTag` include only games/tags with at least one win.
- Further statistics will be added to the same page.
