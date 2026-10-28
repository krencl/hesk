# ADR 0014: Party short name and editing, page title

Status: accepted (2026-10-20)

## Context
The party abbreviation in the round header button was merely derived from the name on the frontend and could not be changed. The party detail said "Jsi správce/člen této party" (You are an admin/member of this party), which is also visible from the member list. Page titles did not differ from subtitles.

## Decision
- **Party short name** is a required column `party.short_name` (1–3 characters; migrations `Version20261020000000` and `…21000000`, which generates the short name for existing parties and tightens the column to NOT NULL). The API returns `shortName` for the party and for the detail.
- **Generation on creation** (`App\Domain\PartyShortName`, the only place; the frontend does not derive the short name): initial letters of the first two words, special characters are omitted and conjunctions/prepositions (a, u, v, na, do, …) are skipped – "Viki + Marek" and "Viki a Marek" → `VM`; one word → first two letters (`De`). The short name is filled in the same way for parties taken over by the import from the old application (an existing short name is not overwritten on repeated import).
- **Party editing:** `PATCH /api/parties/{id}` takes `{name, shortName}` (`UpdatePartyRequest`, both required, short name 1–3 characters after trimming). Admin only. Editing is a separate page `/parties/:id/edit` (`PartyEditPage`) with its own URL; it is reached by the "Upravit partu" (Edit party) button in the party detail (admin only, a regular member is redirected by the page to the detail), and after saving as well as "Zrušit" (Cancel) we return to the detail.
- The name (both on creation and editing) is validated after trimming whitespace, so whitespace alone cannot be saved.
- The text "Jsi admin/člen této party" (You are an admin/member of this party) is gone; the role is visible among the members. The button "Přepnout na tuto partu" (Switch to this party) disappeared from the detail (switching is done in the menu and in party management).
- The party detail heading has a small secondary label "Parta" (Party) before the name (`PageTitle label`), and "Upravit partu" on the edit page; the same pattern as "Parta" in the menu.
- **Page title** (`PageTitle`, e.g. party detail, statistics, party and game management) is larger (1.5 rem) and underlined with a blue line (`--main`).
