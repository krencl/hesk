# ADR 0013: Výchozí hodnoty formuláře zápisu, číslo sezení, tagy s našeptáváním, zrušení míst

Stav: přijato (2026-10-16)

## Kontext
Formulář nového zápisu byl prázdný: číslo sezení se nedalo zadat ani vidět, hráče bylo nutné pokaždé zaškrtat znovu a místo i tagy se psaly jako volný text (tagy čárkou) bez znalosti dosud použitých hodnot. Místo se od tagu v ničem nelišilo, proto se zrušilo.

## Rozhodnutí
**Pořadí polí ve formuláři:** číslo sezení, začátek a konec vedle sebe, hráči, rozšíření, tagy, poznámka.

**Číslo sezení** (backend `SessionAssigner`, `PlayRequest.sessionNumber`)
- `GET /api/parties/{id}/plays/defaults?startedAt&endedAt` vrací `sessionNumber` a `playerIds`. Číslo je číslo sezení, do kterého by zápis v daném čase spadl (24h pravidlo jako při ukládání); jinak o jedno víc než nejvyšší existující. Příklad: poslední sezení 95 → další zápis téhož dne opět 95, zápis o měsíc později 96.
- `tags` jsou tagy posledního zápisu toho sezení (prázdné, když sezení není); formulář je předvyplní.
- `playerIds` jsou hráči posledního zápisu (podle konce hry) toho sezení, nebo `null`, když sezení ještě není (první hra dne). Frontend pak předvybere členy party s účtem (`defaultPlayerIds`); vlastní hráč hry se vždy přidá.
- Formulář číslo předvyplní a při změně začátku/konce ho znovu navrhne, dokud ho uživatel sám nezměnil. **Posílá se jen ručně změněné číslo** (`sessionNumber` v `PlayRequest`); jinak sezení určí backend jako dřív, takže zobrazené číslo odpovídá uloženému a úprava času zápisu dál přesouvá zápis mezi sezeními automaticky.
- Ručně zadané číslo: existuje-li sezení s tímto číslem, zápis se do něj zařadí (i mimo 24h okno, hranice sezení se rozšíří); jinak vznikne nové sezení s tímto číslem (např. navázání na starou řadu). Číslo musí být kladné.

**Místa zrušena** (migrace `Version20261018000000`): entita `Place`, `play.place_id`, pole `place` v API i formuláři zmizely. Stávající místa se v migraci převedou na tagy (jméno se založí jako tag party, pokud tam ještě není, a přiřadí se zápisům, které měly místo); tabulka `place` se smaže. `down()` schéma vrátí, ale data ne.

**Tagy** (`Autocomplete`, `TagInput`, `GET /api/parties/{id}/tags`)
- Žádné tlačítko „přidat“: uživatel buď vybere z nabídky, nebo napíše novou hodnotu, která se založí při uložení zápisu. Nabídka se ukáže po zaostření (jako select) a při psaní se zužuje bez ohledu na velikost písmen a diakritiku.
- Tagy jsou štítky přímo v rámečku inputu (Backspace v prázdném poli odebere poslední). Po přidání tagu input zůstává zaostřený a nabídka otevřená, takže jde vybírat dál; křížek ani nabídka zaostření nebere.
- Tagy jsou štítky s odebráním; psaný text se přidá Enterem, čárkou, výběrem z nabídky nebo opuštěním pole (rozepsaný tag se neztratí při uložení). Velikost písmen se sjednotí s existujícím tagem.
- Vlastní komponenta místo `<datalist>`: jednotný vzhled a chování na mobilech a testovatelnost.

## Důsledky
- Při úpravě zápisu je číslo sezení zobrazené z uloženého zápisu a návrh se nepřepočítává; změna času uvnitř sezení ho nemění, přesun mimo ně posune zápis automaticky (číslo se neposílá).
- Po uložení zápisu se zneplatní seznam tagů i výchozí hodnoty (`refreshAfterPlayChange`).
