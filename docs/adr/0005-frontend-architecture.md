# 0005 – Frontend Architecture

Status: accepted (2026-10-02)

## Decision
- **Mobile-first**, on desktop only a limited width (`--max-width: 480px`), no special wide layouts (per the specification).
- **Layers:** `src/api` (fetch wrapper + types + endpoints) → `src/lib` (pure logic without React, easy to unit-test) → `src/pages` / `src/components` (UI).
- **Server state = TanStack Query**; query keys are `[entity, partyId, …]`, switching the active party invalidates everything except `me`/`parties`. No Redux/Zustand.
- **The active party** lives in `AppContext`, its id is remembered in `localStorage` (`lib/activeParty.ts`); when the stored party disappears, the first available one is used.
- **Routing** (React Router): `/` (plays), `/plays/new`, `/games[/new|/:id]`, `/stats`, `/parties`, `/join/:token`, `/settings`. Unauthenticated → `LoginPage`; without a nickname → `NicknamePage`; without a party → a prompt to create one.
- **Invitation via login:** the `/join/…` path survives the redirect to Google via `sessionStorage` (`lib/afterLogin.ts`).
- **Dev:** Vite proxy `/api` → `http://localhost:8000` (`API_URL`), so the session cookie works without CORS.
- **Charts** (statistics) are custom CSS bars (`BarChart`) – no library until more than two charts are needed.
- **Style:** a single `styles.css` with CSS variables; the color `#277bda` and the Signika font taken from the prototype. No UI frameworks.
- The UI language is Czech only for now, texts directly in components (i18n when more languages are needed).
