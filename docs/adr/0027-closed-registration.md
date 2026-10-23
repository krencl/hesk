# 0027 – Uzavřená registrace

Stav: přijato (2026-10-23)

## Kontext
Aplikace se zatím používá jen v uzavřené partě, ale přihlášení přes Google (ADR 0002) dosud zakládalo účet každému, kdo se přihlásil.

## Rozhodnutí
- Při přihlášení přes Google se **účet nezaloží**, pokud uživatel není v databázi (podle `googleId`, nebo e-mailu účtu převzatého ze staré aplikace) a nepřichází s platnou pozvánkou. Existující uživatelé se přihlašují beze změny.
- Pozvánky (`PartyInvitation`) jsou odkazy s tokenem (`/join/<token>`), nejsou vázané na e-mail. Frontend proto při přihlášení nepřihlášeného uživatele z odkazu pozvánky předá token: `GET /api/auth/google?invitation=<token>` ho uloží do session, `googleCallback` ho po návratu z Googlu přečte (a z session smaže) a nový účet povolí jen u existující a neprošlé pozvánky.
- Odmítnutý nový uživatel je přesměrován na `FRONTEND_URL/?registration=denied` a přihlašovací stránka vysvětlí, že registrace je uzavřená a je potřeba pozvánka.
- `POST /api/auth/dev-login` (jen `dev`/`test`) se nemění.

## Důsledky
- Pozvánku může využít kdokoli, kdo odkaz má (platí 7 dní); omezení na konkrétní e-mail by vyžadovalo změnu pozvánek.
- Nový člen se po registraci do party přidá obvyklým přijetím pozvánky (`/join/<token>`), které si frontend pamatuje přes přihlášení.
