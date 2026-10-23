# 0004 – Doménový model a pravidla

Stav: přijato (2026-10-02)

Vychází z `/hesk-dev-old/SPECIFICATION.md`; terminologie: v kódu *Play* = „game play entry“ (zápis), *GameSession* = seance, *Party* = parta.

## Entity
`User` · `Party` + `PartyMember(role)` + `PartyInvitation` · `Player` · `Game` + `GameAddon` · `PlayTag` · `GameSession` · `Play` + `PlayResult`.

## Rozhodnutí
- **Hry a tagy patří partě** (`party_id`), nejsou globální. Jednoduchá autorizace, žádné kolize mezi partami; cena: stejná hra se zakládá v každé partě zvlášť. (Původní produkční app měla globální katalog.)
- **Parta musí mít alespoň jednoho admina** – vynuceno v `Party::removeMember/changeRole` (výjimka `DomainException` → HTTP 422). Zakladatel je admin automaticky.
- **Pozvánky jsou odkazy s tokenem** (platnost 7 dní), ne e-maily – bez mailové infrastruktury. Kdokoli přihlášený s odkazem se připojí.
- **Typy bodování** (`ScoreType`): `most_points`, `fewest_points`, `rank_order`, `winner_only`. `ResultCalculator` z hrubého vstupu spočítá pořadí a vítěze; remízy sdílí pořadí (1,1,3) a všichni s pořadím 1 jsou vítězové. Typ bodování nelze změnit u hry s odehranými zápisy.
- **Seance**: zápis se přiřadí k seanci party, pokud je mezera mezi ním a hranicemi seance ≤ 24 h (`GameSession::accepts`), jinak vznikne nová; hranice se rozšiřují/přepočítávají při přidání, úpravě i smazání. Dělení seance po smazání zápisu uprostřed se neprovádí.
- **Hráči zápisu** jsou `Player` z party (člen s účtem nebo host, viz ADR 0007; původní předpoklad „hráč = uživatel“ neplatí); rozšíření musí patřit k vybrané hře; tagy se zakládají podle názvu (find-or-create); místa zrušena v ADR 0013 (jsou tagy).
- **Výsledek hráče** (`PlayResult`) ukládá `score` (nullable), `placement` (rank) a `winner`; statistiky se počítají DQL agregací.
- **Časy** se ukládají jako UTC `DATETIME`; API používá ISO 8601 s offsetem, frontend převádí z/do lokálního času.
- **Obrázek hry** je zatím jen URL (`imageUrl`), upload souborů později. **Cheatsheet** je HTML; frontend ho vždy sanitizuje (DOMPurify).
- Smazání hry se zápisy je zakázáno (422).

## Otevřené
- Editace existujícího zápisu ve UI (API `PUT /api/plays/{id}` existuje).
- WYSIWYG editor cheatsheetu, upload obrázku, import dat z původní aplikace (RethinkDB).
