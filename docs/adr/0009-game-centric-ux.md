# ADR 0009: UX podle prototypu – aplikace orientovaná na hry

Stav: přijato (2026-10-14)

## Kontext
ADR 0008 převzal jen vzhled prototypu (`/hesk-dev-old`); domovská stránka zůstala chronologický seznam zápisů. Majitel chce, aby se aplikace podobala prototypu i ovládáním. Prototyp je **orientovaný na hry**: úvodem je seznam her (obálka, název, poslední zápis a vítěz), s hledáním a filtrem; detail hry ukazuje její zápisy a cheatsheet; zápis se zakládá z detailu hry.

## Rozhodnutí
- **Routy:** `/` → `/games` (seznam her), `/games/:id` (detail hry se zápisy), `/games/:id/cheatsheet`, `/games/:id/edit`, `/games/new`. Správa her je na `/manage/games`: hledání a filtr (řazení) jako v seznamu her, výchozí řazení podle názvu, lazy loading; sdílí s ním `GameSearch` a `useGameList`, nastavení se pamatuje zvlášť. Zápis se zakládá jen z detailu hry: `/games/:id/new-play` (hra je daná, ve formuláři se nevybírá); po uložení se vracíme na detail hry. Samostatná chronologie zápisů (`/plays`) a obecný formulář `/plays/new` s výběrem hry byly odstraněny jako redundantní (2026-10-16); zůstal detail zápisu `/plays/:id`.
- **Seznam her:** hledání v názvu, filtr (Řazení: aktivita / název; Sezení: číslo seance), další stránky po 20 se dotahují při rolování (lazy loading, komponenta `LoadMore` s `IntersectionObserverem`; tlačítko „Načíst další“ zůstává jen jako záloha bez observeru a „Zkusit znovu“ po chybě); nastavení přežije návrat ze stránky (`sessionStorage`). Backend `GET /parties/{id}/games` bere `q`, `sort`, `sessionNumber`, `limit`, `offset` a vrací `lastPlay` (datum, číslo seance, vítězové) a `playCount`. Stránkování bez počtu celku: klient žádá o `limit+1` záznamů a podle toho pozná další stránku.
- **Detail hry:** zápisy hry od nejnovějšího (`GET /parties/{id}/plays?gameId=`), první rozbalený, rozbalení ukazuje verzi hry (rozšíření), číslo seance, tagy, poznámku a výsledky; mazání s potvrzením. Tlačítka Nový zápis / Cheatsheet (jen když existuje) / Zpět.
- **Číslo seance** (`game_session.number`, unikátní v partě): stará aplikace měla číslo ručně udržované a s mezerami (parta 1: 66 seancí, čísla do 69), chronologické pořadí by proto ukazovalo jiná čísla než prototyp. Migrace `…20261014000000` číslo přebírá z `legacy_round`, jinak číslováno chronologicky; nová seance dostane `max+1`. Zápisy vrací `sessionNumber`, `GET /parties/{id}/sessions` seznam seancí pro filtr.
- **Cheatsheety jsou Markdown:** prototyp je ukládal jako Markdown (`marked`), import je převzal beze změny. UI je vykresluje přes `marked` a výstup vždy sanitizuje (DOMPurify); protože Markdown propouští HTML, funguje to i pro ručně psané HTML. Data se nemění.
- **Obálky her:** prototyp je servíroval ze souborů (`web/public/img/cover/`), import `cover_image` nepřenesl. V dev prostředí jsou soubory zkopírované do `frontend/public/covers/` (git-ignorováno, ~13 MB) a `game.image_url` je nastaveno na `/static/covers/<soubor>`. Jde o lokální data, ne o funkci – řádné řešení je upload obrázků (ADR 0020, soubory se do `web/uploads/` přemigrovaly).
- Drobnosti z prototypu: pulzující kroužek kolem tlačítka party při načítání, kostry (skeleton) místo „Načítám…“, menu s oddíly Parta / Menu / Správa a podtrženým odhlášením, ikona vítěze `workspace_premium`.

- **Parta: správa a detail jsou oddělené** (2026-10-16): `/parties` = správa part (seznam, přepnutí aktivní, založení nové; po založení se otevře její detail), `/parties/:id` = detail party (členové, změna rolí, odebrání, pozvánky, opuštění). Menu: „Zobrazit detail“ vede na detail aktivní party, „Spravovat party“ na správu. Detail se dá otevřít i pro neaktivní partu.

- **Úprava zápisu místo detailu zápisu** (2026-10-16): stránka detailu zápisu (`/plays/:id`) byla odstraněna (nic nepřidávala k rozbalenému zápisu v detailu hry). V rozbaleném zápisu je jediné tlačítko „Upravit“ → `/plays/:id/edit`, formulář sdílený se založením zápisu (`PlayForm`); „Smazat zápis“ (s potvrzením) je až pod formulářem úpravy. Hru lze upravovat jen ze správy her (`/manage/games` → `/games/:id/edit`), z detailu hry odkaz zmizel.

## Důsledky
- Starý odkaz `/games/:id` (formulář hry) teď vede na detail; formulář je na `/games/:id/edit`.
- Výchozí řazení `GET …/games` se změnilo na „poslední aktivita“ (správa her si žádá `sort=name`).
- Při změně seancí (smazání zápisu, přesun) zůstávají čísla ostatních seancí beze změny; číslo uvolněné smazáním poslední seance se použije znovu.
