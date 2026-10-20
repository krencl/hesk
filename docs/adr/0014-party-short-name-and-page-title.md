# ADR 0014: Zkratka a úprava party, nadpis stránky

Stav: přijato (2026-10-20)

## Kontext
Zkratka party v kulatém tlačítku hlavičky se jen odvozovala z názvu (`partyShortcut`) a nešla změnit. Detail party říkal „Jsi admin/člen této party“, což je vidět i ze seznamu členů. Nadpisy stránek se nelišily od podnadpisů.

## Rozhodnutí
- **Zkratka party** je volitelný sloupec `party.short_name` (až 3 znaky, migrace `Version20261020000000`); bez ní se zkratka odvodí z názvu (`deriveShortcut`), takže stávající party vypadají stejně. API vrací `shortName` u party i u detailu.
- **Úprava party:** `PATCH /api/parties/{id}` bere `{name, shortName}` (`UpdatePartyRequest`; prázdná zkratka = žádná). Smí jen admin. V detailu party je pro admina tlačítko „Upravit partu“, které otevře formulář s názvem a zkratkou (v placeholderu je odvozená zkratka).
- Název (u zakládání i úpravy) se validuje po ořezání mezer, takže jen z mezer se uložit nedá.
- Text „Jsi admin/člen této party“ zmizel; role je vidět u členů.
- **Nadpis stránky** (`PageTitle`, např. detail party, statistiky, správa part a her) je větší (1,5 rem) a podtržený modrou linkou (`--main`).
