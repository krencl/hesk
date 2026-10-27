# 0030 – Nick v partě

Stav: přijato (2026-10-27)

## Kontext
Hráč měl jednu přezdívku účtu, která se propsala do jména jeho hráče ve všech partách. V různých partách ale chce být jmenován různě.

## Rozhodnutí
- `party_member.nickname` (nullable): nick člena jen v dané partě. Jméno člena v partě je `PartyMember::displayName()` = nick v partě, jinak přezdívka účtu („defaultní nick“, použije se i pro nové party).
- Jméno hráče člena (`Player.name`, vidět v zápisech a statistikách) se řídí tímto jménem: `Party::changeMemberNickname` ho přejmenuje (obsazené jméno → `DomainException`, 422), `syncPlayerName` (změna přezdívky účtu) nick v partě nepřebíjí.
- API: `PATCH /api/parties/{id}/me` s `{nickname: string|null}` (prázdný = zpět na přezdívku účtu), vrací detail party; členové v detailu mají `nickname` (efektivní jméno) a `partyNickname` (jen nick v partě).
- UI: Nastavení → „Defaultní nick, použije se pro nové party“ a pod tím pole „Nick v partě <název aktivní party>“ s defaultním nickem jako placeholderem.
- Migrace `Version20261027082517`.
