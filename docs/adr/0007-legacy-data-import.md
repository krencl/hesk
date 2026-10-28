# 0007 – Taking Over Data from the Old Application and the Player Model

Status: accepted (2026-10-03), import code removed (ADR 0029); the migrations from this ADR were replaced by a single initial migration (ADR 0026)

## Context
The dev DB (MariaDB 11) contained the schema of the `hesk-dev-old` prototype with real data (2013–2022: 544 rounds, 2071 results, 5 parties, 4 users, 36 players). The owner allowed free handling of the DB (they have a backup). The data showed that ~17 % of results belong to players **without a user account** (guests) – the "result = user" model (ADR 0004) is not enough.

## Decision
- **New entity `Player`** (belongs to a party, optionally linked to a `User`). `PlayResult` references `Player`, not `User`. A party member gets a player automatically (`Party::ensurePlayerFor`), guests are added by a member via `POST /api/parties/{id}/players`. After a member is removed, the player stays (history) as a guest. The account's nickname is propagated to the player's name if the name is free in the party.
- **Migrations in order:** `…000000` renames the legacy tables to `legacy_*` (only if they exist), `…000100` creates the new schema, `…000200` converts the data one-off. On an empty DB only the schema runs. The legacy tables remain (Doctrine ignores them via `schema_filter`), they can be deleted after verification.
- **Conversion rules:**
  - users and parties are kept including IDs; `owner` → `admin`; nickname = the name of the player linked to the account; accounts have no `google_id` → on first login they are paired by **e-mail** (`User.googleId` is nullable, the user identifier in the session is the e-mail);
  - the old player is global → a new player is created for each (player, party) pair where they played/were a member; "game" players (Boss, T.I.M.E…) are ordinary players;
  - games: the old catalog is global, the new model is per-party → a game is created in each party where it was played (unplayed games stayed only in `legacy_game`); `cover_image` is not carried over (the DB holds only a file name);
  - `game_version` → expansions (the "Základní" (Basic) version does not create an expansion);
  - sessions are taken from the old `session_number` (maintained manually), boundaries from the round times; `started_at = ended_at = created_at` (the old DB had no start/end);
  - times are converted from Europe/Prague to UTC; play IDs = round IDs;
  - the ranking (`rank`) is computed by scoring type (the score for `rank_order` is used as the rank), `winner` is taken over from the old DB;
  - not converted (remains in `legacy_*`): translations, the `party_player/party_tag/user_player` counters, `bgg_id`, party `shortcut`, game tags.
- Verified after migration: the counts (544 plays, 2071 results, 553 tags) match, every play has a winner, `doctrine:schema:validate` is fine, the API returns the converted data.

> _Continuation:_ repeated import from the live old database is handled by ADR 0012 (`app:legacy:import`).

## Consequences
- The migration `…000200` is written in PHP via DBAL (not pure SQL), is not idempotent – it runs once. `down()` only empties the new tables.
- For converted plays the start time = the end time; the list therefore shows only one time.
