# ADR 0019: Víc hráčů hry, editace jménem jako postavy

Stav: přijato (2026-10-21)

## Kontext
Hráč hry (protivník v kooperativních hrách, ADR 0011) byl jeden a ve formuláři hry se vybíral z existujících hostů. Hra ale může mít hráčů víc (např. několik protivníků) a výběr z hostů byl nepraktický oproti postavám, které se prostě píšou jménem.

## Rozhodnutí
- **Víc hráčů hry:** `game.player_id` nahradila vazební tabulka `game_player` (`Game::$players`, many-to-many s `Player`, `ON DELETE CASCADE`). Migrace `Version20261025000000` hodnoty přenese; `down` vrátí nejnižší id.
- **API:** hra vrací `players: [{id, name}]` a bere `players: [{id?, name}]` (jako `addons` a `characters`, `playerId` zaniklo). `id` je **id hráče party**. Pravidla v `Game::update`: položka s `id` přejmenuje hráče (jen hosta; člena s účtem ne, jeho jméno určuje přezdívka), bez `id` se spáruje podle jména s hráčem party (včetně člena), jinak vznikne nový host (`Party::addGuest`). Prázdná a opakovaná jména se zahodí, cizí hráč (jiná parta) se odmítne (422). Odebraný hráč zůstává v partě (Hosté), jen přestane být hráčem hry. Přejmenování sdílí `Party::renamePlayer` s `PATCH /api/players/{id}`.
- **UI:** formulář hry používá `NamedListEditor` („Hráči hry“, Přidat hráče hry, křížek odebere), žádný výběr z existujících hráčů. Formulář nového zápisu předvybere všechny hráče hry a ukáže je první.
- **Sloučení hosta s členem** (ADR 0018) nahradí hosta členem v hráčích her; **smazání hosta** je zakázané, dokud je hráčem nějaké hry. Import ze staré aplikace přidá všechny hráče typu „game“ dané hry, ruční změny v nové aplikaci neodebírá.

## Důsledky
- Přejmenování hráče hry ve formuláři hry přejmenuje hráče party všude (i ve starých zápisech), stejně jako přejmenování postavy.
- Hráč založený z formuláře hry je běžný host party a objeví se i v běžném výběru hostů; skrýt ho jde ve správě hostů.
