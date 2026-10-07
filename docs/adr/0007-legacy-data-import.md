# 0007 – Převzetí dat ze staré aplikace a model Hráče

Stav: přijato (2026-10-03)

## Kontext
Dev DB (MariaDB 11) obsahovala schéma prototypu `hesk-dev-old` s reálnými daty (2013–2022: 544 kol, 2071 výsledků, 5 skupin, 4 uživatelé, 36 hráčů). Majitel povolil s DB nakládat libovolně (má zálohu). Data ukázala, že ~17 % výsledků patří hráčům **bez uživatelského účtu** (hosté) – model „výsledek = uživatel“ (ADR 0004) nestačí.

## Rozhodnutí
- **Nová entita `Player`** (patří skupině, volitelně navázaná na `User`). `PlayResult` odkazuje na `Player`, ne na `User`. Člen skupiny dostane hráče automaticky (`Party::ensurePlayerFor`), hosty přidává člen přes `POST /api/parties/{id}/players`. Po odebrání člena hráč zůstane (historie) jako host. Přezdívka účtu se propisuje do jména hráče, pokud je jméno ve skupině volné.
- **Migrace v pořadí:** `…000000` přejmenuje legacy tabulky na `legacy_*` (jen pokud existují), `…000100` vytvoří nové schéma, `…000200` jednorázově převede data. Na prázdné DB se běží jen schéma. Legacy tabulky zůstávají (Doctrine je ignoruje přes `schema_filter`), lze je po ověření smazat.
- **Pravidla převodu:**
  - uživatelé a skupiny se zachovají včetně ID; `owner` → `admin`; přezdívka = jméno hráče navázaného na účet; účty nemají `google_id` → při prvním přihlášení se spáruje podle **e-mailu** (`User.googleId` je nullable, identifikátor uživatele v session je e-mail);
  - starý hráč je globální → nový hráč vzniká pro každou dvojici (hráč, skupina), kde se hrál/byl členem; „herní“ hráči (Boss, T.I.M.E…) jsou obyčejní hráči;
  - hry: starý katalog je globální, nový model per-skupina → hra se založí v každé skupině, kde se hrála (nehrané hry zůstaly jen v `legacy_game`); `cover_image` se nepřenáší (v DB je jen název souboru);
  - `game_version` → přídavek (verze „Základní“ přídavek nevytvoří);
  - seance se berou ze starého `session_number` (ručně udržované), hranice z časů kol; `started_at = ended_at = created_at` (start/konec ve staré DB nebyly);
  - časy se převádí z Europe/Prague do UTC; ID zápisů = ID kol;
  - pořadí (`rank`) se dopočítá podle typu bodování (skóre u `rank_order` se použije jako pořadí), `winner` se přebírá ze staré DB;
  - nepřevedeno (zůstává v `legacy_*`): postavy, „začínající hráč“, překlady, `party_player/party_tag/user_player` počítadla, `bgg_id`, `shortcut` skupiny, tagy her.
- Ověřeno po migraci: počty (544 zápisů, 2071 výsledků, 553 tagů) sedí, každý zápis má vítěze, `doctrine:schema:validate` je v pořádku, API vrací převedená data.

## Důsledky
- Migrace `…000200` je napsaná v PHP přes DBAL (ne čisté SQL), není idempotentní – spouští se jednou. `down()` jen vyprázdní nové tabulky.
- U převedených zápisů je čas začátku = konci; seznam proto ukazuje jen jeden čas.
