# 0033 – Party data export

Status: accepted (2026-11-02)

## Context
A party admin wants to take the party's data out of the application (backup, analysis elsewhere). The export should be a single JSON document in which every entity has its own top-level key and entities refer to each other by id only (a play lists the ids of its players, not their names).

## Decision
- **UI:** the party management tab **Data** ("Data", `/parties/:id/data`, admin only, like Settings) with the button "Exportovat data". The scope can be chosen: all data, plays by date range, or plays by session range (both ends optional, so "from session 12 on" works). The browser saves the response as `hesk-export-<date>.json`.
- **API:** `GET /api/parties/{id}/export` (`PARTY_ADMIN`) returns the document as an attachment. Query: `from` (inclusive) and `to` (exclusive), both ISO 8601 date-times, **or** `fromSession` and `toSession` (inclusive session numbers). Mixing the two, a backwards range or an invalid value → 422. The frontend turns the picked days into local-midnight instants (the end day + 1), so the server's time zone does not matter.
- **Filter semantics** (`PartyExportFilter`, `PlayRepository::findForExport`): the date range applies to `plays.startedAt`, the session range to the number of the play's session. The filter limits only `sessions` and `plays` (sessions = those of the exported plays). Players, games and play tags are always exported whole, so a filtered export stays self-contained and consistent; game tags only for the exported games (the table is shared by all parties).
- **Document** (`PartyExporter`, `version` 1), top-level keys in this order:
  - `meta`: `format` (`hesk-party-export`), `version`, `exportedAt`, `party {id, name, shortName}`, `filter` (`{type: all}`, `{type: date, from, to}` or `{type: session, from, to}`),
  - `players` `{id, name, visible}` (members and guests alike, no link to accounts),
  - `games` `{id, name, shortName, scoreType, bggId, imageUrl, tagIds}` (games enrolled in the party; no description or cheat sheet; images are not embedded, `imageUrl` is the relative `/uploads/…` address),
  - `gameAddons` and `gameCharacters` `{id, gameId, name}`, `gameTags` `{id, name}`,
  - `partyGames` `{gameId, playerIds}` (the game's own players in the party),
  - `playTags` `{id, name}`,
  - `sessions` `{id, number, startedAt, endedAt}`,
  - `plays` `{id, gameId, sessionId, startedAt, endedAt, notes, addonIds, tagIds, results[{playerId, score, rank, winner, starting, characterId}]}`; results are nested because they have no identity of their own.
- Game ids are those of the shared catalog (ADR 0023), identical across parties; `bggId` helps to match games in another instance.
- **Not exported:** members, roles, accounts, e-mails, invitations, tokens. Players carry no `userId`.
- Times are ISO 8601 with offset, rows are sorted by id (plays by start, sessions by number, results by rank), so exports of unchanged data are comparable.

## Consequences
- The query is only checked manually against the DB (like the other SQL, ADR 0022); the document assembly, the filter and the controller have unit tests.
- A change of the document shape must raise `PartyExporter::VERSION`.
- There is no import; the format is meant to be read by other tools.
