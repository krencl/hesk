# Documentation

Primarily architecture decision records (ADRs). Application documentation is deliberately minimal – the source of truth is the code and the tests.

Docs are written in English.

| ADR | Topic |
| --- | --- |
| [0001](adr/0001-technology-stack.md) | Technology choices (Symfony 7.4 LTS, Doctrine/MySQL, React + Vite) |
| [0002](adr/0002-authentication.md) | Authentication (Google OAuth, session cookie) |
| [0003](adr/0003-testing-strategy.md) | Testing strategy |
| [0004](adr/0004-domain-model.md) | Domain model and rules |
| [0005](adr/0005-frontend-architecture.md) | Frontend architecture |
| [0006](adr/0006-configuration-and-deployment.md) | Configuration, database (MariaDB) and deployment |
| [0007](adr/0007-legacy-data-import.md) | Taking over data from the old application, the Player model |
| [0008](adr/0008-ui-look-and-play-detail.md) | Look based on the old prototype, play detail |
| [0009](adr/0009-game-centric-ux.md) | UX based on the prototype: game list, game detail, session numbers, Markdown cheatsheets |
| [0010](adr/0010-ui-consistency-and-play-flags.md) | Unified UI skeleton (headings, 2×2 buttons), winner and starting player in a play |
| [0011](adr/0011-game-extras.md) | Short name, expansions, characters and game player |
| [0012](adr/0012-repeatable-legacy-import.md) | Repeatable import from the old application (removed, ADR 0029) |
| [0013](adr/0013-play-form-defaults.md) | Play form defaults: session number, preselected players, tags with autocomplete, removal of locations |
| [0014](adr/0014-party-short-name-and-page-title.md) | Party short name and editing, page heading with a blue line |
| [0015](adr/0015-menu-sticky-actions-toasts.md) | Menu, sticky main buttons, toast messages |
| [0016](adr/0016-party-management-tabs-and-guests.md) | Party management in tabs, guest management |
| [0017](adr/0017-party-roles.md) | Party roles: member, scorekeeper, admin |
| [0018](adr/0018-guest-to-member-linking.md) | Turning a guest into a member: merging a guest with a member, invitation for a guest |
| [0019](adr/0019-multiple-game-players.md) | Multiple game players, editing by name as a character |
| [0020](adr/0020-game-image-upload.md) | Game image upload with square cropping |
| [0021](adr/0021-bgg-game-search.md) | Creating games via BoardGameGeek (party games → cache → BGG API) |
| [0022](adr/0022-backend-tests-without-database.md) | Backend tests without a database (mocks instead of SQLite) |
| [0023](adr/0023-shared-game-catalog.md) | Shared game catalog: one game for all parties, party↔game link, game admin |
| [0024](adr/0024-game-tags-and-stats-filter.md) | Game tags (in the catalog) and a statistics filter by them |
| [0025](adr/0025-name-comparison.md) | Name comparison by `NameKey` (case- and diacritics-insensitive, like the DB collation), 409 on uniqueness violation |
| [0026](adr/0026-squashed-initial-migration.md) | A single initial migration instead of history (before the first deployment) |
| [0027](adr/0027-closed-registration.md) | Closed registration: a new account only with an invitation |
| [0028](adr/0028-move-play-between-parties.md) | Moving a play to another party (admin of both parties, players by account/name, new guests) |
| [0029](adr/0029-production-config-and-legacy-removal.md) | Production configuration, removal of the import from the old application |
| [0030](adr/0030-nickname-per-party.md) | A member's nickname in only one party (default nickname × party nickname) |
| [0031](adr/0031-my-stats.md) | My statistics: games with the most wins, wins by game tag |

Notes on the reference material from previous versions: [prototype-notes.md](prototype-notes.md).

A new decision = a new file `adr/NNNN-slug.md` (Context / Decision / Consequences) and a row in the table above.
