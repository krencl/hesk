# 0028 – Moving a play to another party

Status: accepted (2026-10-24)

## Context
A play is occasionally created by mistake in a different party (one with similar players), and after the import from the old application the split of plays between parties needs to be corrected. A play (`Play`) belongs to one party, as do its sessions (`GameSession`), players (`Player`) and tags (`PlayTag`); the game, expansions and characters are in the shared catalog (ADR 0023).

## Decision
- `POST /api/plays/{id}/move` with `{partyId}`; allowed only for an **administrator of both the source and the target party**. A nonexistent target party and a party where the caller is not an administrator are not distinguished (422), so that one cannot find out which parties exist.
- The logic is in the `PlayMover` service and runs in a single transaction:
  - **Game** is enlisted in the target party (`Party::addGame`) if it is not there yet; it stays in the source party. Expansions and characters belong to the game, so they do not change. If the play's player is also a "game player" (ADR 0019) in the source party, they become one in the target party too.
  - **Players** (`PlayResult.player`): a member with an account is found by the account, otherwise a **guest of the target party with the same name** is looked up (`NameKey`, ADR 0025) and treated as the same person; anyone not there is created as a **guest** (with the same visibility). A name taken by a member with a different account gets a number ("Bob 2"), because names within a party are unique; renaming and linking it is then possible in guest management (ADR 0018). One player of the target party cannot be used twice (unique play + player).
  - **Tags** are created/found again in the target party by name.
  - **Session**: the original session is recalculated without the moved play (an empty one disappears), the play is assigned to a session of the target party by the 24-hour rule (`SessionAssigner`, the number is assigned automatically).
- **Preview before confirmation:** `GET /api/plays/{id}/move-preview?partyId=` (same permissions) returns `{party, gameAdded, players: [{playerId, name, targetName, fromMember, toMember, created}]}`. It is computed by `PlayMover::plan()` – the very same function that `move()` then uses (so the two cannot diverge) – and changes nothing. The popup before the "Přesunout" (Move) button lists for each player "Name → target name" and what happens (member/guest unchanged, from member to guest, new guest) and whether the game gets enlisted in the target party; until the preview loads, the button is disabled.
- Response: `{play, party: {id, name}, createdGuests: [names]}`. The source party also loses the play in its statistics; guests and players left without plays by this are not deleted.
- Frontend: below the play edit form there is a link "Přesunout do jiné party" (Move to another party) (only administrators who are administrators of at least one more party) → a popup with party selection → confirmation → a result with the names of newly created guests.

## Consequences
- A move can also be done back (the administrator must be in both parties), but session numbers are assigned anew.
- A member with an account who is not in the target party appears there as a guest; after they are added to the party, the guest can be merged with the member (ADR 0018).
