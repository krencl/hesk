# ADR 0018: A guest becomes a member

Status: accepted (2026-10-21)

## Context
A guest (a player without an account) often starts using the application themselves over time. When they are added as a member, a new player is created for them and the history of the original guest would stay orphaned. It needs to be transferred to their account, also retroactively for people who are already members.

## Decision
- **Transfer = merging the guest into the member's player** (`GuestMerger`): all results of the guest (`play_result`) and "game players" (`game_player`, ADR 0019) are rewritten to the member's player (which stays, so their name keeps following the nickname) and the guest is deleted. After the guest is deleted, their name is free, and the member's player who got a name like "Bob 2" on adding because of a collision is renamed to the nickname. If the guest and the member played in the same play (uniqueness play + player), the merge is rejected (422) and nothing changes.
- **Retroactively** (`POST /api/players/{id}/link-member`, `{userId}`, admin only): in Guests a button with a link icon on the guest → popup "Propojit s členem" (Link with member) → choose a member → confirmation of what will happen (plays transfer, the guest disappears, cannot be undone).
- **When adding to the party:** an invitation can be intended for a guest (`POST /parties/{id}/invitations` with `{guestId}`, only a guest of the same party; `party_invitation.guest_id`, migration `Version20261024000000`). After it is accepted, the guest is merged with the new member; if that is not possible (shared play) or the guest no longer exists, the invitee simply joins. The response contains `linkedGuest` (the guest's name or `null`). The link is valid for 7 days and after the merge the invitation loses its link to the guest, so another person with the same link simply joins. The invitation is created by the same popup in Guests ("Pozvat jako člena" (Invite as member)).
- Deleting a guest (also merging) explicitly nulls the invitations' link to the guest, so that no reference to a nonexistent player remains.
