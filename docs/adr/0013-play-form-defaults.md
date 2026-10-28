# ADR 0013: Play form defaults, session number, tags with suggestions, removal of places

Status: accepted (2026-10-16)

## Context
The new play form was empty: the session number could be neither entered nor seen, players had to be ticked again every time, and both place and tags were typed as free text (tags separated by commas) without knowing the values used so far. A place did not differ from a tag in any way, so it was removed.

## Decision
**Field order in the form:** session number, start and end side by side, players, expansions, tags, note.

**Session number** (backend `SessionAssigner`, `PlayRequest.sessionNumber`)
- `GET /api/parties/{id}/plays/defaults?startedAt&endedAt` returns `sessionNumber` and `playerIds`. The number is the number of the session the play would fall into at the given time (the 24h rule as when saving); otherwise one more than the highest existing one. Example: last session 95 → the next play on the same day is again 95, a play a month later is 96.
- `tags` are the tags of the last play of that session (empty when the session does not exist); the form pre-fills them.
- `playerIds` are the players of the last play (by game end) of that session, or `null` when the session does not exist yet (first game of the day). The frontend then preselects party members with an account (`defaultPlayerIds`); the game's own player is always added.
- The form pre-fills the number and proposes it again when the start/end changes, until the user changes it manually. **Only a manually changed number is sent** (`sessionNumber` in `PlayRequest`); otherwise the backend determines the session as before, so the displayed number matches the stored one and editing a play's time still moves the play between sessions automatically.
- Manually entered number: if a session with this number exists, the play is placed into it (even outside the 24h window, the session boundaries are extended); otherwise a new session with this number is created (e.g. continuing an old series). The number must be positive.

**Places removed** (migration `Version20261018000000`): the `Place` entity, `play.place_id` and the `place` field in the API and form are gone. Existing places are converted to tags in the migration (the name is created as a party tag if not yet there and assigned to plays that had the place); the `place` table is dropped. `down()` restores the schema but not the data.

**Tags** (`Autocomplete`, `TagInput`, `GET /api/parties/{id}/tags`)
- No "add" button: the user either picks from the suggestions or types a new value, which is created when the play is saved. The suggestions appear on focus (like a select) and narrow as you type, ignoring case and diacritics.
- Tags are chips directly inside the input box (Backspace in an empty field removes the last one). After adding a tag the input stays focused and the suggestions stay open, so you can keep selecting; neither the cross nor the suggestions take focus.
- Tags are chips with a remove button; typed text is added with Enter, a comma, a pick from the suggestions or leaving the field (a half-typed tag is not lost on save). Letter case is unified with an existing tag.
- A custom component instead of `<datalist>`: uniform look and behavior on mobile and testability.

## Consequences
- When editing a play, the session number is shown from the stored play and the suggestion is not recomputed; changing the time within the session does not change it, moving outside it shifts the play automatically (the number is not sent).
- After a play is saved, the tag list and the defaults are invalidated (`refreshAfterPlayChange`).
