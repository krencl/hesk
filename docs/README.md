# Dokumentace

Primárně architektonická rozhodnutí (ADR). Aplikační dokumentace je záměrně minimální – zdrojem pravdy je kód a testy.

| ADR | Téma |
| --- | --- |
| [0001](adr/0001-technology-stack.md) | Volba technologií (Symfony 7.4 LTS, Doctrine/MySQL, React + Vite) |
| [0002](adr/0002-authentication.md) | Autentizace (Google OAuth, session cookie) |
| [0003](adr/0003-testing-strategy.md) | Strategie testování |
| [0004](adr/0004-domain-model.md) | Doménový model a pravidla |
| [0005](adr/0005-frontend-architecture.md) | Architektura frontendu |
| [0006](adr/0006-configuration-and-deployment.md) | Konfigurace, databáze (MariaDB) a nasazení |
| [0007](adr/0007-legacy-data-import.md) | Převzetí dat ze staré aplikace, model Hráče |

Poznámky ke vzoru z předchozích verzí: [prototype-notes.md](prototype-notes.md).

Nové rozhodnutí = nový soubor `adr/NNNN-slug.md` (Kontext / Rozhodnutí / Důsledky) a řádek v tabulce výše.
