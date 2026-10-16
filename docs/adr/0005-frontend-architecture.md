# 0005 – Architektura frontendu

Stav: přijato (2026-10-02)

## Rozhodnutí
- **Mobile-first**, na desktopu jen omezená šířka (`--max-width: 480px`), žádné speciální široké layouty (dle specifikace).
- **Vrstvy:** `src/api` (fetch obálka + typy + endpointy) → `src/lib` (čistá logika bez Reactu, snadno unit-testovaná) → `src/pages` / `src/components` (UI).
- **Server state = TanStack Query**; klíče dotazů jsou `[entita, partyId, …]`, přepnutí aktivní party zneplatní vše kromě `me`/`parties`. Žádný Redux/Zustand.
- **Aktivní parta** žije v `AppContext`, id se pamatuje v `localStorage` (`lib/activeParty.ts`); když uložená parta zmizí, použije se první dostupná.
- **Routing** (React Router): `/` (zápisy), `/plays/new`, `/games[/new|/:id]`, `/stats`, `/parties`, `/join/:token`, `/settings`. Neautentizovaný → `LoginPage`; bez přezdívky → `NicknamePage`; bez party → výzva k vytvoření.
- **Pozvánka přes login:** cesta `/join/…` se přežije přesměrování na Google přes `sessionStorage` (`lib/afterLogin.ts`).
- **Dev:** Vite proxy `/api` → `http://localhost:8000` (`API_URL`), takže session cookie funguje bez CORS.
- **Grafy** (statistiky) jsou vlastní CSS sloupce (`BarChart`) – bez knihovny, dokud nebude potřeba víc než dva grafy.
- **Styl:** jeden `styles.css` s CSS proměnnými; barva `#277bda` a font Signika převzaté z prototypu. Žádné UI frameworky.
- Jazyk UI je zatím pouze čeština, texty přímo v komponentách (i18n až bude potřeba více jazyků).
