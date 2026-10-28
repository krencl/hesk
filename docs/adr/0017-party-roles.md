# ADR 0017: Party roles (member, writer, admin)

Status: accepted (2026-10-23)

## Context
Until then the role was only admin / member and every member could write plays. We need to distinguish members who only read the results.

## Decision
- **Roles:** `admin` = **Správce** (Admin) (manages the party and can do everything; "Správce" in the UI, `admin` remains in code and API), `writer` = **Zapisovač** (Writer) (adds, edits and deletes game plays) and `member` = **Člen** (Member) (only reads plays). Games, guests, statistics and reading are visible to all members as before.
- **Backend:** `PartyRole::Writer`, the permission `PartyVoter::WRITE` (admin or writer) is required for creating, editing and deleting a play and for `GET /parties/{id}/plays/defaults` (pre-filling a new play); reading a play stays `VIEW`. Party management (`ADMIN`) does not change. A party must always have an admin: changing the last admin to any other role is rejected.
- **Migration** `Version20261023000000`: existing members (`member`) become writers, because everyone could write and they would otherwise lose that. Newly added members (by invitation) are `member`, the admin promotes them. The import from the old application maps owners to admins and others to ordinary members (the admin decides writers in the new application); existing roles are only promoted to admin, roles changed in the new application are not overwritten.
- **Frontend:** in the member list the admin changes the role via a popup (`SelectPopup` without search: Člen / Zapisovač / Správce), others see the role as text. A member has no "Nový zápis" (New play) button on the game detail nor "Upravit" (Edit) on a play, and the new/edited play pages show them an explanation instead of the form (`canWrite` in `lib/roles.ts`).

## Consequences
- ~~Guest management stays open to every member~~ (2026-10-22): adding, renaming, hiding and deleting a guest requires `PARTY_WRITE` (writer and admin); an ordinary member only sees guests in the player picker, and the Guests tab is not offered to them. The same applies to party game management (ADR 0023).
