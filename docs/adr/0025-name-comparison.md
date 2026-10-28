# 0025 – Name comparison: `NameKey` following the database collation

Status: accepted (2026-10-23)

## Context
Names of party players, game tags and play tags have a unique key in the database (`player`, `play_tag`: party + name, `game_tag`: name) and the columns have the collation `utf8mb4_general_ci`, which is insensitive to case **and to diacritics** ("Čeněk" = "cenek"). The code, however, compared names only case-insensitively (`strcasecmp`, `mb_strtolower`), so "Šimon" next to "Simon" passed the domain check and only the DB rejected it with an exception – the client got HTTP 500.

## Decision
- `App\Domain\NameKey::of()` returns the name key (trimmed, without diacritics, lowercase; `symfony/string`). **Every** comparison and merging of names (party players, game players, expansions, characters, tags) goes through it.
- `App\Domain\NamedItems` holds the common cleanup of lists of named items (`normalize`, `uniqueNames`) and the matching of existing entities by key (`resolve`); `Party::isPlayerNameTaken()` is the single check of whether a player name is taken.
- Looking up/creating tags is in `GameTagRepository::findOrCreate()` and `PlayTagRepository::findOrCreate()` (one query `WHERE name IN (…)`, match by key, result without duplicates).
- Safety net: `UniqueConstraintViolationException` under `/api` is returned by `ApiExceptionListener` as HTTP 409 `{error: "Záznam se stejným názvem už existuje."}` ("A record with the same name already exists.") instead of 500 (in case the DB collation merged even names that `NameKey` distinguishes).

## Consequences
- `NameKey` is stricter than the DB: it also transliterates other scripts (e.g. "Иван" = "ivan") and "ß" = "ss", so the code rejects such pairs even though the DB would tolerate them. For names within a party this does not matter; the opposite direction (the DB merges more than `NameKey`) is covered by 409.
- Comparison in PHP is O(number of party players); for small parties (units to tens of players) negligible.
