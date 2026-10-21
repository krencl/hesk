# ADR 0017: Role v partě (člen, zapisovač, správce)

Stav: přijato (2026-10-23)

## Kontext
Do té doby byla role jen admin / člen a zapisovat mohl každý člen. Potřeba je rozlišit členy, kteří výsledky jen čtou.

## Rozhodnutí
- **Role:** `admin` = **Správce** (spravuje partu a může vše; v UI „Správce“, v kódu a API zůstává `admin`), `writer` = **Zapisovač** (přidává, upravuje a maže zápisy her) a `member` = **Člen** (zápisy jen čte). Hry, hosty, statistiky a čtení vidí všichni členové jako dřív.
- **Backend:** `PartyRole::Writer`, oprávnění `PartyVoter::WRITE` (admin nebo zapisovač) vyžaduje vytvoření, úprava a smazání zápisu i `GET /parties/{id}/plays/defaults` (předvyplnění nového zápisu); čtení zápisu zůstává `VIEW`. Správa party (`ADMIN`) se nemění. Parta musí mít vždy admina: změna posledního admina na jakoukoli jinou roli se odmítne.
- **Migrace** `Version20261023000000`: dosavadní členové (`member`) se mění na zapisovače, protože zapisovat mohli všichni a jinak by o to přišli. Nově přidaní členy (pozvánkou) jsou `member`, admin je povyšuje. Import ze staré aplikace mapuje vlastníky na adminy a ostatní na obyčejné členy (zapisovatele určuje admin v nové aplikaci); existující role jen povyšuje na admina, nepřepisuje role změněné v nové aplikaci.
- **Frontend:** v seznamu členů admin mění roli popupem (`SelectPopup` bez hledání: Člen / Zapisovač / Správce), ostatní vidí roli jako text. Člen nemá na detailu hry tlačítko „Nový zápis“ ani „Upravit“ u zápisu a stránky nového/upraveného zápisu mu místo formuláře ukážou vysvětlení (`canWrite` v `lib/roles.ts`).

## Důsledky
- Správa hostů (přidání, přejmenování, skrytí, smazání) zůstává otevřená každému členu; zpřísnit ji na zapisovače a admina je jedna změna v `PlayerController`.
