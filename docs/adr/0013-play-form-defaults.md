# ADR 0013: Výchozí hodnoty formuláře zápisu, číslo sezení, místo a tagy s našeptáváním

Stav: přijato (2026-10-16)

## Kontext
Formulář nového zápisu byl prázdný: číslo sezení se nedalo zadat ani vidět, hráče bylo nutné pokaždé zaškrtat znovu a místo i tagy se psaly jako volný text (tagy čárkou) bez znalosti dosud použitých hodnot.

## Rozhodnutí
**Číslo sezení** (backend `SessionAssigner`, `PlayRequest.sessionNumber`)
- `GET /api/parties/{id}/plays/defaults?startedAt&endedAt` vrací `sessionNumber` a `playerIds`. Číslo je číslo sezení, do kterého by zápis v daném čase spadl (24h pravidlo jako při ukládání); jinak o jedno víc než nejvyšší existující. Příklad: poslední sezení 95 → další zápis téhož dne opět 95, zápis o měsíc později 96.
- `playerIds` jsou hráči posledního zápisu (podle konce hry) toho sezení, nebo `null`, když sezení ještě není (první hra dne). Frontend pak předvybere členy party s účtem (`defaultPlayerIds`); vlastní hráč hry se vždy přidá.
- Formulář číslo předvyplní a při změně začátku/konce ho znovu navrhne, dokud ho uživatel sám nezměnil. **Posílá se jen ručně změněné číslo** (`sessionNumber` v `PlayRequest`); jinak sezení určí backend jako dřív, takže zobrazené číslo odpovídá uloženému a úprava času zápisu dál přesouvá zápis mezi sezeními automaticky.
- Ručně zadané číslo: existuje-li sezení s tímto číslem, zápis se do něj zařadí (i mimo 24h okno, hranice sezení se rozšíří); jinak vznikne nové sezení s tímto číslem (např. navázání na starou řadu). Číslo musí být kladné.

**Místo a tagy** (`Autocomplete`, `TagInput`, `GET /api/parties/{id}/places|tags`)
- Žádné tlačítko „přidat“: uživatel buď vybere z nabídky, nebo napíše novou hodnotu, která se založí při uložení zápisu (backend už `findOrCreate` dělal). Nabídka se ukáže po zaostření (jako select) a při psaní se zužuje bez ohledu na velikost písmen a diakritiku.
- Tagy jsou štítky s odebráním; psaný text se přidá Enterem, čárkou, výběrem z nabídky nebo opuštěním pole (rozepsaný tag se neztratí při uložení). Velikost písmen se sjednotí s existujícím tagem.
- Vlastní komponenta místo `<datalist>`: jednotný vzhled a chování na mobilech a testovatelnost.

## Důsledky
- Při úpravě zápisu je číslo sezení zobrazené z uloženého zápisu a návrh se nepřepočítává; změna času uvnitř sezení ho nemění, přesun mimo ně posune zápis automaticky (číslo se neposílá).
- Po uložení zápisu se zneplatní seznamy míst/tagů i výchozí hodnoty (`refreshAfterPlayChange`).
