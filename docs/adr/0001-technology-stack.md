# 0001 – Technology Choices

Status: accepted (2026-10-02)

## Context
New version of HESK (records of board game plays). Assignment: React + TypeScript frontend, REST API in PHP (Symfony preferred), relational data in MySQL, unit tests on both sides.

## Decision
- **Backend: Symfony 7.4 LTS** (PHP ≥ 8.4), Doctrine ORM + Migrations, Symfony Security, Validator, Serializer (`#[MapRequestPayload]`), PHPUnit.
  - **LTS 7.4** was chosen, not 8.1: 8.1 is not LTS (support ends 01/2027), 7.4 is supported until 11/2028.
  - Laravel/Slim was considered as an alternative; Symfony stays because the client prefers it and the prototype (`hesk-dev-old`) already used Symfony. Doctrine replaces Dibi from the prototype (mapping, migrations, testability).
- **Frontend: React 19 + TypeScript + Vite**, React Router, TanStack Query (server state), Vitest + Testing Library. No additional global state library.
- **DB: MariaDB 11** (the existing dev/prod server; `backend/compose.yaml` for local MySQL/MariaDB), schema versioned by Doctrine migrations.
- **Repo structure:** monorepo – `backend/`, `frontend/`, `docs/`. Frontend and backend are deployed independently and communicate only via `/api`.

## Consequences
- Two projects, two dependency managers (composer, npm).
- Entities use only portable Doctrine types (because of tests on SQLite, see ADR 0003).
- A development environment without PHP can be set up with a static PHP binary; it is not part of the project.
