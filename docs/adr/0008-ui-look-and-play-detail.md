# ADR 0008: Vzhled podle starého prototypu a detail zápisu

## Kontext
Majitel chce, aby nová aplikace vypadala jako běžící starý vývojový prototyp (`/hesk-dev-old`, Vue + Bootstrap). Zároveň chyběl detail odehrané hry (backend `GET /api/plays/{id}` už existoval, UI ne).

## Rozhodnutí
- Přebíráme **vzhled**, ne strukturu starého UI: logo v hlavičce, kulaté tlačítko aktivní party vpravo nahoře (zkratka 1–2 znaky) otevírající celoobrazovkové průhledné menu (přepínání part je v něm), ostré rohy (`0.1rem`), bootstrapová tlačítka a formuláře, barvy `#277bda / #777 / #c4dbff`, font Signika, ikony Material Symbols (z Google Fonts), šířka 400 px, seznamy s velkým čtvercovým náhledem (7,2 rem) a modrým hoverem. Bootstrap samotný nepřidáváme – stačí vlastní CSS (`styles.css`), aby bundle zůstal malý.
- Logo a placeholder obálky se kopírují do `frontend/public/` (ze starého prototypu).
- Zkratka party se **počítá z názvu** (`lib/partyShortcut.ts`), protože stará `shortcut` se při importu nepřenesla (ADR 0007).
- Detail zápisu `/plays/:id` (`PlayDetailPage`): hra, kdy/kde, přídavky, tagy, poznámka, výsledky s trofejí u vítězů a skóre/pořadím podle typu bodování; mazání s dvoukrokovým potvrzením. Seznam zápisů na úvodní stránce na detail odkazuje (mazání ze seznamu zmizelo).

## Důsledky
- _Překonáno ADR 0009:_ domovská stránka je teď seznam her a detail hry se zápisy existuje; chronologie zápisů je na `/plays`.
- U zápisů importovaných ze staré DB je začátek = konec, detail proto ukazuje jen jeden čas a bez délky.
- Ikony závisí na načtení písma z Google Fonts; offline se místo nich zobrazí text ligatury.
