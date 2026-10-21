# ADR 0018: Z hosta se stane člen

Stav: přijato (2026-10-21)

## Kontext
Host (hráč bez účtu) často časem začne aplikaci používat sám. Když se přidá jako člen, vznikne mu nový hráč a historie původního hosta by zůstala osiřelá. Potřeba je ji převést na jeho účet, a to i zpětně u lidí, kteří už jsou členy.

## Rozhodnutí
- **Převod = sloučení hosta do hráče člena** (`GuestMerger`): všechny výsledky hosta (`play_result`) a „hráč hry“ (`game.player`) se přepíšou na hráče člena (ten zůstává, takže jeho jméno dál sleduje přezdívku) a host se smaže. Po smazání hosta je jeho jméno volné, a hráč člena, který při přidání dostal kvůli kolizi jméno typu „Bob 2“, se přejmenuje na přezdívku. Pokud host a člen hráli ve stejném zápisu (unikát zápis + hráč), sloučení se odmítne (422) a nic se nezmění.
- **Zpětně** (`POST /api/players/{id}/link-member`, `{userId}`, jen admin): v Hostech tlačítko s ikonou odkazu u hosta → popup „Propojit s členem“ → výběr člena → potvrzení, co se stane (zápisy přejdou, host zmizí, nejde zpět).
- **Při přidání do party:** pozvánka může být určená hostovi (`POST /parties/{id}/invitations` s `{guestId}`, jen host téže party; `party_invitation.guest_id`, migrace `Version20261024000000`). Po jejím přijetí se host sloučí s novým členem; pokud to nejde (společný zápis) nebo host už neexistuje, pozvaný se prostě jen připojí. Odpověď obsahuje `linkedGuest` (jméno hosta nebo `null`). Odkaz platí 7 dní a po sloučení pozvánka ztratí vazbu na hosta, takže další člověk se stejným odkazem se jen připojí. Pozvánku vytváří stejný popup v Hostech („Pozvat jako člena“).
- Mazání hosta (i sloučení) pozvánkám vynuluje vazbu na hosta explicitně, aby nezůstal odkaz na neexistujícího hráče.
