# ADR 0010: Jednotná kostra UI a příznaky výsledku (vítěz, začínající hráč)

Stav: přijato (2026-10-16)

## Kontext
UI vznikalo po částech a nesedělo si: různé velikosti nadpisů, odsazení záhlaví hry mezi detailem a formulářem, tlačítka v několika podobách a velikostech, v zápisu chyběla volba vítěze při shodě skóre i začínající hráč.

## Rozhodnutí
**Kostra stránky** (`frontend/src/styles.css`, komponenty `PageTitle`, `GameHeader`, `Actions`)
- Každá stránka je `<section class="page">` bez vlastního odsazení; obsah je v blocích `.pad` (1 rem po stranách, 1 rem nad blokem), seznamy a záhlaví jdou přes celou šířku.
- Nadpisy `h2`/`h3` mají vždy 1,25 rem, váhu 500 a barvu textu (žádné šedé podnadpisy) – stejné písmo jako název hry. Záhlaví stránky je `PageTitle` (text) nebo `GameHeader` (náhled + název hry, i ve formulářích zápisu); oba sdílí třídu `.page-head`.
- **Hlavní tlačítka (CTA) jsou vždy v mřížce 2×2** (`Actions`), stejně velká, nejvýše čtyři: 1. vlevo nahoře (primární, `btn`), 2. vpravo nahoře (sekundární, `btn outline`), 3. vpravo dole (terciální, `btn outline-secondary`), 4. vlevo dole. Malá tlačítka (`btn small`) jsou jen vložené ovladače v řádcích a menu (Přepnout, Přidat hosty). Destruktivní akce (smazání hry/zápisu) jsou šedý podtržený text pod tlačítky, ne CTA.
- Aktivní položka seznamu (parta ve správě part, rozbalený zápis) se označuje pozadím a levým proužkem, ne tlačítkem ani štítkem.

**Formulář zápisu** (`PlayForm`, sdílený pro nový zápis i úpravu)
- Bez rámečků kolem polí. Hráči: nahoře **hlavní hráči** (členy s účtem), hosté až po „Přidat hosty“ (vybraní hosté jsou vidět vždy).
- U každého vybraného hráče přepínače **Vítěz** (kromě pořadí) a **Začíná** (nejvýše jeden hráč).
- **Vítěz u her se skóre:** výchozí je automatika (nejlepší skóre, při shodě všichni se shodou). Ruční zásah posílá vítěze explicitně (`winner` u hráčů) a backend ho respektuje i při shodě nebo nepravidelnostech; „Vítěze určit podle skóre“ se vrací k automatice. Pořadí (`rank`) se i tak počítá ze skóre.
- Backend: `play_result.is_starting` (sloupec nese `is_` kvůli rezervovanému slovu `STARTING`), `PlayerRequest.starting`/`winner`, validace „nejvýše jeden začínající“. Migrace přebírá začínající hráče ze `legacy_round_player` (144 zápisů), spárování podle pozice výsledku v zápisu.

## Důsledky
- Detail hry ukazuje u hráče ikonu vítěze i začínajícího (dvě pevné pozice, aby jména lícovala).
- Úprava hry se po uložení/smazání vrací do správy her (`/manage/games`), odkud je jediný vstup.
- Při 3 tlačítkách zůstává pole vlevo dole prázdné (pozice jsou pevné).
