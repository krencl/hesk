# 0001 – Volba technologií

Stav: přijato (2026-10-02)

## Kontext
Nová verze HESK (zápisy z deskových her). Zadání: React + TypeScript frontend, REST API v PHP (preferovaný Symfony), relační data v MySQL, unit testy na obou stranách.

## Rozhodnutí
- **Backend: Symfony 7.4 LTS** (PHP ≥ 8.4), Doctrine ORM + Migrations, Symfony Security, Validator, Serializer (`#[MapRequestPayload]`), PHPUnit.
  - Zvolena **LTS 7.4**, ne 8.1: 8.1 není LTS (podpora končí 01/2027), 7.4 je podporována do 11/2028.
  - Alternativa Laravel/Slim zvážena; Symfony zůstává, protože ho zadavatel preferuje a prototyp (`hesk-dev-old`) už Symfony používal. Dibi z prototypu nahrazuje Doctrine (mapování, migrace, testovatelnost).
- **Frontend: React 19 + TypeScript + Vite**, React Router, TanStack Query (server state), Vitest + Testing Library. Žádná globální state knihovna navíc.
- **DB: MariaDB 11** (stávající dev/prod server; `backend/compose.yaml` pro lokální MySQL/MariaDB), schéma verzováno Doctrine migracemi.
- **Struktura repa:** monorepo – `backend/`, `frontend/`, `docs/`. Frontend a backend se nasazují nezávisle, komunikují jen přes `/api`.

## Důsledky
- Dva projekty, dva správce závislostí (composer, npm).
- Entity používají jen přenosné typy Doctrine (kvůli testům na SQLite, viz ADR 0003).
- Vývojové prostředí bez PHP lze zprovoznit statickým PHP binárem; není to součást projektu.
