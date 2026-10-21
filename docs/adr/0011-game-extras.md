# ADR 0011: Zkrácený název, rozšíření, postavy a hráč hry

Stav: přijato (2026-10-16)

## Kontext
Správa her (inspirace aktuálním HESK, `/hesk-original`, nastavení her) nenabízela zkrácený název (statistiky), pohodlnou editaci rozšíření, postavy ani vlastního hráče hry (kooperativní hry, kde lidé hrají proti hře – Boss, T.I.M.E…).

## Rozhodnutí
- **Zkrácený název** `game.short_name` (volitelný, max. 50): statistiky ukazují `shortName ?? name`.
- **Rozšíření** (dříve „přídavky“, v kódu stále `addon`) a **postavy** (`GameCharacter`) se ukládají jako seznam `{id?, name}`. Položka s `id` se přejmenuje (zachovají se vazby na odehrané zápisy), bez `id` se spáruje s existující stejného názvu, jinak vznikne nová; chybějící se odeberou (vazby na zápisy se tím odpojí). Prázdné a opakované názvy se zahodí. UI: `NamedListEditor` – samostatné pole pro každou položku, Enter přidá řádek, křížek odebere.
- **Postava ve výsledku zápisu:** `play_result.character_id` (volitelná, musí patřit ke hře zápisu); formulář zápisu nabídne výběr postavy u hráče, detail hry ji ukazuje vedle jména.
- **Hráč hry** `game.player_id` (hráč té samé party, typicky host): ve formuláři zápisu je vždy vidět, první v seznamu a rovnou vybraný; ve formuláři hry se edituje **jménem** jako postavy (textové pole, žádný výběr z existujících hráčů): změna jména přejmenuje stávajícího hosta, jméno u hry bez hráče založí nového hosta, prázdné pole hráče od hry odpojí (host zůstane v Hostech party).
- **Obrázek hry:** `imageUrl` smí být `http(s)://…` nebo lokální cesta začínající `/` (např. `/static/covers/…`); jiná schémata (`javascript:`, `data:`) a `//host` se odmítají. Předtím nešla uložit hra s lokální obálkou.
- **Import z dev DB** (`Version20261017000000`): zkrácené názvy (jen pokud se liší od názvu), postavy (pro každou novou hru), postavy ve výsledcích (191 výsledků, párování podle pozice v zápisu) a hráči her (typ „game“ ve staré DB, 3 hry).

## Důsledky
- Zrušení postavy odpojí její výběr ve starších zápisech (`ON DELETE SET NULL`), stejně jako zrušení rozšíření odpojí rozšíření od zápisů.
- API pro hru už neakceptuje rozšíření jako pole řetězců, ale pole objektů `{id?, name}`.
