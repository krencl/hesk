# 0030 – Nickname per party

Status: accepted (2026-10-27)

## Context
A player had a single account nickname, which carried over into the name of their player in all parties. In different parties, however, they want to be called differently.

## Decision
- `party_member.nickname` (nullable): the member's nickname only in the given party. A member's name in the party is `PartyMember::displayName()` = the nickname in the party, otherwise the account nickname ("default nickname", also used for new parties).
- The member's player name (`Player.name`, visible in plays and statistics) follows this name: `Party::changeMemberNickname` renames it (a taken name → `DomainException`, 422), `syncPlayerName` (change of the account nickname) does not override the nickname in the party.
- API: `PATCH /api/parties/{id}/me` with `{nickname: string|null}` (empty = back to the account nickname), returns the party detail; members in the detail have `nickname` (the effective name) and `partyNickname` (only the nickname in the party).
- UI: Settings → "Defaultní nick, použije se pro nové party" (Default nickname, used for new parties) and below it a field "Nick v partě <name of the active party>" (Nickname in the party) with the default nickname as the placeholder.
- Migration `Version20261027082517`.
