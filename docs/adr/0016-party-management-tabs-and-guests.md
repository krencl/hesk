# ADR 0016: Správa party po záložkách, správa hostů

Stav: přijato (2026-10-20)

## Kontext
Správa party (členové, pozvánky) a úprava názvu stály vedle sebe jako detail s tlačítkem „Upravit partu“. Přibyla správa hostů (hráčů bez účtu) a bylo potřeba domyslet navigaci.

## Rozhodnutí
- **Záložky jako samostatné stránky** (`PartyHeader`): záhlaví „Parta <název>“ a pod ním přepínač **Členové** (`/parties/:id`) · **Hosté** (`/parties/:id/guests`) · **Nastavení** (`/parties/:id/edit`, jen admin). Každá sekce má vlastní adresu (odkaz, historie, přepnutí party v menu zachová sekci). Tlačítko „Upravit partu“ zaniklo, nahradila ho záložka Nastavení. Jednostránkové rozbalovací sekce jsem nezvolil: dlouhá stránka s několika formuláři by měla víc Uložit tlačítek najednou a hlavní tlačítka (ADR 0015) jsou jedna lišta na stránku.
- **Hosté** (`PartyGuestsPage`, přístupné každému členu party): seznam hráčů bez účtu s počtem zápisů; jména se upravují přímo v seznamu a ukládají hromadně tlačítkem Uložit (jen změněná), nový host se přidává polem dole (Enter nebo Přidat), smazat jde jen host bez zápisů.
- **Backend:** `GET /parties/{id}/players` vrací `playCount`; `DELETE /players/{id}` smaže jen hosta, který nikde nehrál a není hráčem žádné hry; přejmenování hráče navázaného na účet se odmítá (jeho jméno následuje přezdívku); jména složená jen z mezer se odmítají. Člen, který party opustí nebo je odebrán, se stává hostem (zápisy zůstávají), takže ho lze v Hostech přejmenovat.
