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
- API chyby i validační hlášky formulářů se zobrazují jako toast (`ToastProvider`, `ErrorMessage`, `useToasts().showError`): červená (`#da2736`, 90 %) s `backdrop-filter` jako menu, výška stejná jako záhlaví s logem (5 rem), shora dolů pod sebou, víc najednou, každá s křížkem na místě tlačítka party. Mizí po 6 s (+1 s za každou podrobnost); dole má každá odpočítávací proužek, který zároveň odděluje zprávy pod sebou. Po najetí myší se odpočet i proužek zastaví, stejný text se nenaskládá dvakrát. Zavřená zpráva se nejdřív svine na nulovou výšku (grid `1fr → 0fr`), takže zprávy pod ní plynule sjedou nahoru.
- `ErrorMessage` sám nic nevykresluje; `inline` varianta zůstala pro stránky, které kvůli chybě nemají jiný obsah (nenačetl se detail hry, party, zápisu).

**Filtr seznamu her (popup místo selectu)**
- Řazení a Sezení v panelu filtru jsou tlačítka s aktuální volbou (`SelectPopup`, podle `common-select` ze starého prototypu). Klik otevře popup přes celou šířku aplikace, který překryje obsah (bílá s rozmazáním), nahoře s názvem volby a hledáním, které má ihned focus, a pod ním seznam voleb s přepínačem. Hledání nezohledňuje velikost písmen ani diakritiku; vybraná volba zůstane vidět (zeslabená) i mimo výsledky a „Všechny“ (bez filtru) je vždy první.
- Zavření: výběr volby, Esc, nebo křížek, který stojí na místě kulatého tlačítka party (to popup překrývá, jako se v prototypu mění na zavírací). Zaostření se vrací na tlačítko.
- Ostatní `<select>`y (typ bodování hry, hráč hry, postava hráče) zůstávají nativní.

**Tooltipy tlačítek s ikonou**
- Jedna globální vrstva `TooltipLayer` (v `App`) sleduje najetí myší a zaostření klávesnicí a ukáže vlastní tooltip u každého tlačítka či odkazu, který obsahuje jen ikonu (text bere z `aria-label`, takže se tlačítka s ikonou nemusí nijak označovat a nové tooltip dostanou samy). `data-tooltip` text přebije (prázdný ho vypne) a funguje i u tlačítka s textem; nativní `title` se nepoužívá.
- Tmavý popisek se šipkou nad prvkem (pod ním, když nahoře není místo), v portálu nad vším ostatním, vodorovně držený v okně; objeví se po 350 ms (při zaostření klávesnicí hned) s lehkou animací a zmizí při odjetí, kliknutí, scrollu, Esc. Po kliknutí myší tooltip nezůstane viset, na dotykových zařízeních (`hover: none`) se nezobrazuje.
