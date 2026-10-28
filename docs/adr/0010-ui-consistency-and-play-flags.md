# ADR 0010: Unified UI Skeleton and Result Flags (Winner, Starting Player)

Status: accepted (2026-10-16)

## Context
The UI was created piece by piece and did not fit together: different heading sizes, different indentation of the game header between the detail and the form, buttons in several forms and sizes, and the play lacked a winner choice for tied scores as well as a starting player.

## Decision
**Page skeleton** (`frontend/src/styles.css`, components `PageTitle`, `GameHeader`, `Actions`)
- Every page is a `<section class="page">` without its own padding; content is in `.pad` blocks (1 rem at the sides, 1 rem above the block), lists and headers span the full width.
- `h2`/`h3` headings are always 1.25 rem, weight 500 and the text color (no gray subheadings) – the same font as the game name. Exception (ADR 0014): the `PageTitle` page heading is 1.5 rem and underlined with a blue line. The page header is `PageTitle` (text) or `GameHeader` (thumbnail + game name, also in play forms); both share the class `.page-head`.
- **Main buttons (CTAs) are always in a 2×2 grid** (and stuck to the bottom of the screen, ADR 0015) (`Actions`), equally sized, at most four: 1. top left (primary, `btn`), 2. top right (secondary, `btn outline`), 3. bottom right (tertiary, `btn outline-secondary`), 4. bottom left. Small buttons (`btn small`) are only embedded controls in rows and menus (Switch, Add guests). Destructive actions (deleting a game/play) are gray underlined text below the buttons, not CTAs.
- The active list item (party in party management, expanded play) is marked by a background and a left stripe, not by a button or a label.

**Play form** (`PlayForm`, shared for a new play and editing)
- No borders around fields. Players: at the top the **main players** (members with an account), guests only after "Přidat hosty" (Add guests) (selected guests are always visible).
- For each selected player the toggles **Vítěz** (Winner; besides the rank) and **Začíná** (Starts; at most one player).
- **Winner in games with a score:** the default is automatic (best score, in case of a tie everyone tied). A manual override sends the winner explicitly (`winner` on players) and the backend respects it even for a tie or irregularities; "Vítěze určit podle skóre" (Determine the winner by score) returns to automatic. The rank (`rank`) is still computed from the score.
- Backend: `play_result.is_starting` (the column carries `is_` because of the reserved word `STARTING`), `PlayerRequest.starting`/`winner`, validation "at most one starting player". The migration takes over starting players from `legacy_round_player` (144 plays), matched by the result's position in the play.

## Consequences
- The game detail shows the winner and starting-player icons in one (narrow) column; if a player has both, the winner is in front with an outline in the background color and the starting one peeks out a bit behind it on the right.
- Game names in the list are 1.1 rem with line height 1.15, so the row height (image 7.2 rem) does not change even for a two-line name.
- Game editing returns to game management (`/manage/games`) after saving/deleting, which is the only entry point.
- With 3 buttons the bottom-left slot stays empty (positions are fixed).
