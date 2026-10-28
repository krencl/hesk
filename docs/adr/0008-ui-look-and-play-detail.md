# ADR 0008: Look Based on the Old Prototype and Play Detail

## Context
The owner wants the new application to look like the running old development prototype (`/hesk-dev-old`, Vue + Bootstrap). At the same time, a detail of a recorded game was missing (the backend `GET /api/plays/{id}` already existed, the UI did not).

## Decision
- We take over the **look**, not the structure of the old UI: logo in the header, a round active-party button at the top right (1–2 character abbreviation) opening a full-screen transparent menu (party switching is in it), sharp corners (`0.1rem`), Bootstrap-style buttons and forms, colors `#277bda / #777 / #c4dbff`, Signika font, Material Symbols icons (from Google Fonts), 400 px width, lists with a large square thumbnail (7.2 rem) and a blue hover. We do not add Bootstrap itself – custom CSS (`styles.css`) is enough, so that the bundle stays small.
- The logo and the cover placeholder are copied to `frontend/public/` (from the old prototype).
- The party abbreviation is **computed from the name** (`lib/partyShortcut.ts`), because the old `shortcut` was not carried over during import (ADR 0007).
- Play detail `/plays/:id` (`PlayDetailPage`): game, when/where, expansions, tags, note, results with a trophy for winners and score/rank depending on the scoring type; deletion with two-step confirmation. The play list on the home page links to the detail (deletion from the list disappeared).

> _Superseded by ADR 0009:_ the play detail `/plays/:id` was removed and replaced by play editing.

## Consequences
- _Superseded by ADR 0009:_ the home page is now the game list and a game detail with plays exists; the chronology of plays is at `/plays`.
- For plays imported from the old DB the start = the end, so the detail shows only one time and no duration.
- Icons depend on loading the font from Google Fonts; offline, the ligature text is shown instead of them.
