# 0022 – Backend tests without a database (mocks instead of SQLite)

Status: accepted (2026-10-22)

## Context
The functional API tests (`ApiFlowTest`, `BggFlowTest`) went through whole HTTP flows on top of SQLite and recreated the schema for every test. On a slow disk (fsync on every commit) a run took over 5 minutes, and the tests depended on database state.

## Decision
- Backend tests do not touch the database. Controllers, services and repositories are tested as unit tests (`tests/Unit/Controller`, `tests/Unit/Service`, …); `EntityManager`, repositories and queries are mocks/stubs (`tests/Support/DoctrineMocks`), entities live in memory (`tests/Support/Fixtures` fills in the `id` that the DB would assign).
- Controllers are called directly via `tests/Support/ControllerHarness`: a logged-in user, the real `PartyVoter` and evaluation of `#[IsGranted]` on the action (which the kernel would otherwise do), so permissions (403) are verified too.
- Input validation (`tests/Unit/Dto/RequestValidationTest`), conversion of exceptions to JSON (`ApiExceptionListenerTest`) and access rules (`ApiAccessTest`) have their own tests without a kernel.
- (Until 2026-10-24, ADR 0029) The only exception was `tests/Functional/LegacyImportTest`: the import is SQL from a foreign database into the application schema and a mock would not verify it. It runs on SQLite, the schema is created once per class (`TestDatabase`), and fsync is disabled in the test environment (`SqliteNoFsyncMiddleware`).
- Classes stay `final`; mocking them is made possible by `dg/bypass-finals` (a dev dependency, a PHPUnit extension in `phpunit.dist.xml`).

## Consequences / limits
- A run of the whole suite takes ~1 s instead of minutes.
- Whatever exists only in SQL/DQL is not tested: sorting and searching of games (`LIKE` escaping), statistics aggregation, `GameRepository::statsFor`, `PlayRepository`, unique keys, cascades, entity mapping and migrations. These layers currently have no automated test; they can be verified manually against the dev DB, or later by a small set of repository tests with SQLite/MariaDB (see the limits in ADR 0003).
- Request deserialization (`#[MapRequestPayload]`, invalid enum, invalid JSON) is not exercised in the unit tests.
