# 0004 – Domain Model and Rules

Status: accepted (2026-10-02)

Based on `/hesk-dev-old/SPECIFICATION.md`; terminology: in code *Play* = "game play entry" (zápis), *GameSession* = session (seance), *Party* = party (parta).

## Entities
`User` · `Party` + `PartyMember(role)` + `PartyInvitation` · `Player` · `Game` + `GameAddon` · `PlayTag` · `GameSession` · `Play` + `PlayResult`.

## Decision
- **Games and tags belong to a party** (`party_id`), they are not global. Simple authorization, no collisions between parties; cost: the same game is created in each party separately. (The original production app had a global catalog.)
- **A party must have at least one admin** – enforced in `Party::removeMember/changeRole` (`DomainException` → HTTP 422). The founder is an admin automatically.
- **Invitations are links with a token** (valid for 7 days), not e-mails – no mail infrastructure. Anyone logged in with the link joins.
- **Scoring types** (`ScoreType`): `most_points`, `fewest_points`, `rank_order`, `winner_only`. `ResultCalculator` computes the ranking and winners from raw input; ties share the rank (1,1,3) and everyone with rank 1 is a winner. The scoring type cannot be changed for a game with recorded plays.
- **Session**: a play is assigned to a party's session if the gap between it and the session's boundaries is ≤ 24 h (`GameSession::accepts`), otherwise a new one is created; boundaries are extended/recomputed on add, edit and delete. Splitting a session after deleting a play in the middle is not done.
- **Play players** are `Player`s from the party (a member with an account or a guest, see ADR 0007; the original assumption "player = user" no longer holds); an expansion must belong to the selected game; tags are created by name (find-or-create); locations were cancelled in ADR 0013 (they are tags).
- **A player's result** (`PlayResult`) stores `score` (nullable), `placement` (rank) and `winner`; statistics are computed by DQL aggregation.
- **Times** are stored as UTC `DATETIME`; the API uses ISO 8601 with an offset, the frontend converts from/to local time.
- **Game image** is only a URL for now (`imageUrl`), file upload later. **Cheatsheet** is HTML; the frontend always sanitizes it (DOMPurify).
- Deleting a game with plays is forbidden (422).

## Open
- Editing an existing play in the UI (the API `PUT /api/plays/{id}` exists).
- WYSIWYG cheatsheet editor, image upload, data import from the original application (RethinkDB).
