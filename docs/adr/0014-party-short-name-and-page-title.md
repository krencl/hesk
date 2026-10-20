# ADR 0014: Zkratka a úprava party, nadpis stránky

Stav: přijato (2026-10-20)

## Kontext
Zkratka party v kulatém tlačítku hlavičky se jen odvozovala z názvu na frontendu a nešla změnit. Detail party říkal „Jsi admin/člen této party“, což je vidět i ze seznamu členů. Nadpisy stránek se nelišily od podnadpisů.

## Rozhodnutí
- **Zkratka party** je povinný sloupec `party.short_name` (1–3 znaky; migrace `Version20261020000000` a `…21000000`, která stávajícím partám zkratku vygeneruje a sloupec zpřísní na NOT NULL). API vrací `shortName` u party i u detailu.
- **Generování při založení** (`App\Domain\PartyShortName`, jediné místo; frontend zkratku neodvozuje): počáteční písmena prvních dvou slov, speciální znaky se vynechají a spojky/předložky (a, u, v, na, do, …) se přeskočí – „Viki + Marek“ i „Viki a Marek“ → `VM`; jedno slovo → první dvě písmena (`De`). Stejně se zkratka dosadí partám převzatým importem ze staré aplikace (existující zkratka se při opakovaném importu nepřepisuje).
- **Úprava party:** `PATCH /api/parties/{id}` bere `{name, shortName}` (`UpdatePartyRequest`, obojí povinné, zkratka 1–3 znaky po ořezání mezer). Smí jen admin. V detailu party je pro admina tlačítko „Upravit partu“, které otevře formulář s názvem a zkratkou.
- Název (u zakládání i úpravy) se validuje po ořezání mezer, takže jen z mezer se uložit nedá.
- Text „Jsi admin/člen této party“ zmizel; role je vidět u členů.
- **Nadpis stránky** (`PageTitle`, např. detail party, statistiky, správa part a her) je větší (1,5 rem) a podtržený modrou linkou (`--main`).
