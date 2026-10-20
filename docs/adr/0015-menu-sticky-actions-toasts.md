# ADR 0015: Menu, přichycená hlavní tlačítka a toast zprávy

Stav: přijato (2026-10-20)

## Rozhodnutí
**Menu**
- Kliknutí na kteroukoli položku menu menu zavře; důležité hlavně u položky aktivní stránky, kde se adresa nezmění a menu by samo nezmizelo.
- Tlačítko na zavření menu zůstává modré jako kulaté tlačítko party (jako ve starém prototypu), ne šedé.
- V sekci Parta je „Statistiky“ a pak „Spravovat“ (detail party).
- Všechny hover efekty (menu, seznamy, ikony, tlačítka) mají stejnou lehkou animaci 0,15 s jako tlačítka.

**Hlavní tlačítka (`Actions`)**
- Lišta je `position: sticky; bottom: 0` s průhledným bílým rozmazaným pozadím. Na desktopu se tak přichytí jen tehdy, když je stránka delší než obrazovka; na mobilu (do 576 px) stránka vyplní obrazovku a lišta je vždy u spodního okraje i na krátké stránce.
- Sticky se drží jen uvnitř svého rodiče, proto je `Actions` vždy přímo ve stránce (`.page`) nebo ve formuláři, ne v malém bloku jen kolem sebe. Tlačítko uvnitř obsahu (např. „Upravit“ v rozbaleném zápisu) je `<Actions inline>` a nepřichytává se.

**Chybové zprávy**
- API chyby i validační hlášky formulářů se zobrazují jako toast (`ToastProvider`, `ErrorMessage`, `useToasts().showError`): červená (`#da2736`, 90 %) s `backdrop-filter` jako menu, výška stejná jako záhlaví s logem (5 rem), shora dolů pod sebou, víc najednou, každá s křížkem na místě tlačítka party. Mizí po 4 s (+1 s za každou podrobnost); dole má každá odpočítávací proužek, který zároveň odděluje zprávy pod sebou. Po najetí myší se odpočet i proužek zastaví, stejný text se nenaskládá dvakrát. Zavřená zpráva se nejdřív svine na nulovou výšku (grid `1fr → 0fr`), takže zprávy pod ní plynule sjedou nahoru.
- `ErrorMessage` sám nic nevykresluje; `inline` varianta zůstala pro stránky, které kvůli chybě nemají jiný obsah (nenačetl se detail hry, party, zápisu).
