# Poznámky ke vzorům

## `/hesk-original` (produkce)
Node.js + Express + RethinkDB + AngularJS. Globální katalog her, role admin přes heslo, „rounds“ s `scoring` typy `score | lowestScore | order | check`, verze hry, postavy, „první hráč“, tagy. Z toho nové zadání přebírá typy bodování (mapování: `score`→`most_points`, `lowestScore`→`fewest_points`, `order`→`rank_order`, `check`→`winner_only`).

## `/hesk-dev-old` (rozpracovaný prototyp)
Symfony 5 + Dibi + Vue 3. Koncept „Party“, mobilní GUI (max. šířka 400 px, hlavní barva `#277bda`, font Signika, kulatý přepínač party v hlavičce), `SPECIFICATION.md`. Adresář leží mimo repozitář (jen referenční).

## Převzato / změněno
- Převzato: koncept part a rolí, mobile-first, top bar s přepínačem party, statistiky (výhry, nejhranější hry), barvy a font.
- Nově: Doctrine místo Dibi, React místo Vue, seance (24 h), rozšíření místo „verzí hry“.
- Zatím nepřevzato z originálu: postavy, „začínající hráč“, porovnání her, filtry – kandidáti na další iterace (po ujasnění s uživatelem).
