# ADR 0011: Short name, expansions, characters and game player

Status: accepted (2026-10-16)

## Context
Game management (inspired by the current HESK, `/hesk-original`, game settings) offered no short name (for statistics), no convenient editing of expansions, no characters and no game's own player (cooperative games where people play against the game – Boss, T.I.M.E…).

## Decision
- **Short name** `game.short_name` (optional, max. 50): statistics show `shortName ?? name`.
- **Expansions** (formerly "přídavky" (add-ons), still `addon` in code) and **characters** (`GameCharacter`) are stored as a list of `{id?, name}`. An item with `id` is renamed (links to played records are kept); without `id` it is matched to an existing one with the same name, otherwise a new one is created; missing ones are removed (their links to records are detached). Empty and duplicate names are discarded. UI: `NamedListEditor` – a separate field for each item, Enter adds a row, the cross removes it.
- **Character in a play result:** `play_result.character_id` (optional, must belong to the game of the play); the play form offers a character choice for each player, the game detail shows it next to the name.
- **Game player** (originally a single `game.player_id`, since ADR 0019 multiple players via `game_player`): a player of the same party, typically a guest; in the play form always visible, first in the list and preselected. Editing in the game form: see [ADR 0019](0019-multiple-game-players.md).
- **Game image:** originally `imageUrl` as any `http(s)://…` or local path; since ADR 0020 it may only be the address of an uploaded WebP (`/uploads/covers/<sha1>.webp`).
- **Import from the dev DB** (`Version20261017000000`): short names (only if different from the name), characters (for each new game), characters in results (191 results, matched by position in the play) and game players (type "game" in the old DB, 3 games).

## Consequences
- Deleting a character detaches its selection in older plays (`ON DELETE SET NULL`), just as deleting an expansion detaches the expansion from plays.
- The game API no longer accepts expansions as an array of strings, but an array of objects `{id?, name}`.
