# 0004 – Doménový model a pravidla

Stav: přijato (2026-10-02)

Vychází z `hesk-tmp/SPECIFICATION.md`; terminologie: v kódu *Play* = „game play entry“ (zápis), *GameSession* = seance, *Party* = skupina.

## Entity
`User` · `Party` + `PartyMember(role)` + `PartyInvitation` · `Game` + `GameAddon` · `Place`, `Tag` · `GameSession` · `Play` + `PlayResult`.

## Rozhodnutí
- **Hry, místa a tagy patří skupině** (`party_id`), nejsou globální. Jednoduchá autorizace, žádné kolize mezi skupinami; cena: stejná hra se zakládá v každé skupině zvlášť. (Původní produkční app měla globální katalog.)
- **Skupina musí mít alespoň jednoho admina** – vynuceno v `Party::removeMember/changeRole` (výjimka `DomainException` → HTTP 422). Zakladatel je admin automaticky.
- **Pozvánky jsou odkazy s tokenem** (platnost 7 dní), ne e-maily – bez mailové infrastruktury. Kdokoli přihlášený s odkazem se připojí.
- **Typy bodování** (`ScoreType`): `most_points`, `fewest_points`, `rank_order`, `winner_only`. `ResultCalculator` z hrubého vstupu spočítá pořadí a vítěze; remízy sdílí pořadí (1,1,3) a všichni s pořadím 1 jsou vítězové. Typ bodování nelze změnit u hry s odehranými zápisy.
- **Seance**: zápis se přiřadí k seanci skupiny, pokud je mezera mezi ním a hranicemi seance ≤ 24 h (`GameSession::accepts`), jinak vznikne nová; hranice se rozšiřují/přepočítávají při přidání, úpravě i smazání. Dělení seance po smazání zápisu uprostřed se neprovádí.
- **Hráči zápisu musí být členy skupiny**; přídavky musí patřit k vybrané hře; místo a tagy se zakládají podle názvu (find-or-create).
- **Výsledek hráče** (`PlayResult`) ukládá `score` (nullable), `placement` (rank) a `winner`; statistiky se počítají DQL agregací.
- **Časy** se ukládají jako UTC `DATETIME`; API používá ISO 8601 s offsetem, frontend převádí z/do lokálního času.
- **Obrázek hry** je zatím jen URL (`imageUrl`), upload souborů později. **Taháček** je HTML; frontend ho vždy sanitizuje (DOMPurify).
- Smazání hry se zápisy je zakázáno (422).

## Otevřené
- Editace existujícího zápisu ve UI (API `PUT /api/plays/{id}` existuje).
- WYSIWYG editor taháčku, upload obrázku, import dat z původní aplikace (RethinkDB).
