# 0028 – Přesun zápisu do jiné party

Stav: přijato (2026-10-24)

## Kontext
Zápis se občas omylem založí v jiné partě (s podobnými hráči) a po importu ze staré aplikace je potřeba napravit rozdělení zápisů mezi party. Zápis (`Play`) patří jedné partě, stejně jako jeho seance (`GameSession`), hráči (`Player`) a tagy (`PlayTag`); hra, rozšíření a postavy jsou ve sdíleném katalogu (ADR 0023).

## Rozhodnutí
- `POST /api/plays/{id}/move` s `{partyId}`; smí to jen **správce zdrojové i cílové party**. Neexistující cílová parta a parta, kde volající není správce, se nerozlišují (422), aby šlo zjistit, jaké party existují.
- Logika je ve službě `PlayMover` a běží v jedné transakci:
  - **Hra** se do cílové party zařadí (`Party::addGame`), pokud tam ještě není; ve zdrojové partě zůstává. Rozšíření a postavy patří hře, takže se nemění. Je-li hráč zápisu zároveň „hráčem hry“ (ADR 0019) ve zdrojové partě, stane se jím i v cílové.
  - **Hráči** (`PlayResult.player`): člen s účtem se najde podle účtu, jinak se hledá **host cílové party se stejným jménem** (`NameKey`, ADR 0025) a považuje se za tutéž osobu; kdo tam není, vznikne jako **host** (se stejnou viditelností). Jméno obsazené členem s jiným účtem dostane číslo („Bob 2“), protože jména v partě jsou unikátní; přejmenovat a propojit ho jde pak ve správě hostů (ADR 0018). Jednoho hráče cílové party nelze použít dvakrát (unikát zápis + hráč).
  - **Tagy** se znovu založí/najdou v cílové partě podle názvu.
  - **Seance**: původní seance se přepočítá bez přesouvaného zápisu (prázdná zanikne), zápis se zařadí do seance cílové party podle 24h pravidla (`SessionAssigner`, číslo se přidělí automaticky).
- **Náhled před potvrzením:** `GET /api/plays/{id}/move-preview?partyId=` (stejná oprávnění) vrátí `{party, gameAdded, players: [{playerId, name, targetName, fromMember, toMember, created}]}`. Počítá ho `PlayMover::plan()` – tatáž funkce, kterou pak používá `move()` (nic se tedy nerozejde), a nic nemění. Popup před tlačítkem „Přesunout“ vypíše u každého hráče „Jméno → cílové jméno“ a co se stane (člen/host beze změny, z člena na hosta, nový host) a zda se do cílové party zařadí hra; dokud se náhled nenačte, jde tlačítko zablokované.
- Odpověď: `{play, party: {id, name}, createdGuests: [jména]}`. Zdrojová parta o zápis přijde i ve statistikách; hosté a hráči, kteří tím zůstali bez zápisů, se nemažou.
- Frontend: pod formulářem úpravy zápisu odkaz „Přesunout do jiné party“ (jen správci, kteří jsou správci aspoň v jedné další partě) → popup s výběrem party → potvrzení → výsledek se jmény nově založených hostů.

## Důsledky
- Přesun jde provést i zpět (správce musí být v obou partách), čísla seancí se ale přidělí znovu.
- Člen s účtem, který v cílové partě není, se tam objeví jako host; po jeho přidání do party ho jde sloučit s členem (ADR 0018).
