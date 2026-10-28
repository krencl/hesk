# Notes on the Reference Versions

> The directories `/hesk-original` and `/hesk-dev-old` were removed on 2026-10-24 after the new application was deployed to production (https://hesk.krencl.cz/); the notes remain as history.

## `/hesk-original` (production)
Node.js + Express + RethinkDB + AngularJS. Global game catalog, admin role via password, "rounds" with `scoring` types `score | lowestScore | order | check`, game version, characters, "first player", tags. The new assignment takes over the scoring types from it (mapping: `score`→`most_points`, `lowestScore`→`fewest_points`, `order`→`rank_order`, `check`→`winner_only`).

## `/hesk-dev-old` (work-in-progress prototype)
Symfony 5 + Dibi + Vue 3. The "Party" concept, mobile GUI (max. width 400 px, main color `#277bda`, Signika font, round party switcher in the header), `SPECIFICATION.md`. The directory lives outside the repository (reference only).

## Taken over / changed
- Taken over: the concept of parties and roles, mobile-first, top bar with a party switcher, statistics (wins, most-played games), colors and font.
- New: Doctrine instead of Dibi, React instead of Vue, sessions (24 h), expansions instead of "game versions".
- Not taken over from the original yet: characters, "starting player", game comparison, filters – candidates for further iterations (after clarifying with the user).
