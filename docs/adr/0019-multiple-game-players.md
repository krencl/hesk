# ADR 0019: Multiple game players, editing by name like characters

Status: accepted (2026-10-21)

## Context
The game player (the opponent in cooperative games, ADR 0011) was a single one and was picked from existing guests in the game form. But a game can have more players (e.g. several opponents) and picking from guests was impractical compared to characters, which are simply typed by name.

## Decision
- **Multiple game players:** `game.player_id` was replaced by the join table `game_player` (`Game::$players`, many-to-many with `Player`, `ON DELETE CASCADE`). Migration `Version20261025000000` carries the values over; `down` restores the lowest id.
- **API:** a game returns `players: [{id, name}]` and takes `players: [{id?, name}]` (like `addons` and `characters`, `playerId` is gone). `id` is the **party player id**. Rules in `Game::update`: an item with `id` renames the player (guests only; not a member with an account, whose name is determined by the nickname), without `id` it is matched by name to a party player (including a member), otherwise a new guest is created (`Party::addGuest`). Empty and duplicate names are discarded, a foreign player (another party) is rejected (422). A removed player stays in the party (Guests), it just stops being a game player. Renaming shares `Party::renamePlayer` with `PATCH /api/players/{id}`.
- **UI:** the game form uses `NamedListEditor` ("Hráči hry" (Game players), "Přidat hráče hry" (Add game player), the cross removes), no picking from existing players. The new play form preselects all game players and shows them first.
- **Merging a guest with a member** (ADR 0018) replaces the guest with the member in game players; **deleting a guest** is forbidden while they are a player of some game. The import from the old application adds all players of type "game" of the given game, and does not remove manual changes made in the new application.

## Consequences
- Renaming a game player in the game form renames the party player everywhere (also in old plays), just like renaming a character.
- A player created from the game form is an ordinary `Player` without an account in the database, but **is not shown in party management (Guests tab)**: `GET /api/parties/{id}/players` returns `gamePlayer` for each player (is a player of at least one party game, `PartyGameRepository::gamePlayerIds`) and Guests omits such players. They are managed only in the game form. In the play form they are visible only for their own game (as a party member, preselected and first); in plays of other games they are not offered among guests (one already selected stays). Removing them from the game players turns them back into an ordinary guest.
