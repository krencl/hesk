# ADR 0020: Nahrání obrázku hry s oříznutím na čtverec

Stav: přijato (2026-10-21)

## Kontext
Obrázek hry byl jen URL (ADR 0004, 0011) a obálky ze staré aplikace jsou lokální soubory bez uploadu. Ve správě her má jít obrázek nahrát a rovnou vybrat čtvercový výřez, protože všude (seznam, detail, statistiky) se zobrazuje jako čtverec (`object-fit: cover`).

## Rozhodnutí
- **Editor v prohlížeči:** výběr souboru otevře popup `ImageCropPopup` (stejný základ jako `SelectPopup`) s knihovnou `react-easy-crop`: čtvercový výřez, tažení a přiblížení (posuvník, na dotyku štípnutí). Po „Použít“ `cropToBlob` (canvas) vyřízne výřez, zmenší ho na 600 × 600 px a zakóduje jako JPEG (kvalita 0,88, průhlednost na bílou), takže se přenáší jen ~100 kB a orientaci z EXIF řeší prohlížeč.
- **Upload:** `POST /api/parties/{id}/game-images` (multipart `file`, smí člen party jako ostatní správa her) vrací `{url}`. `CoverStorage` ověří, že jde o JPEG/PNG/WebP (podle obsahu, ne přípony), je čtvercový, má 100–2048 px a nejvýš 2 MB (jinak 422), a uloží ho pod `sha1` obsahu (stejný soubor = stejná adresa, žádné duplicity). Server obrázek nepřekódovává.
- **Úložiště:** `web/uploads/covers/<sha1>.<jpg|png|webp>` (parametr `app.uploads_dir`, v testech `backend/var/test-uploads`), adresa `/uploads/covers/…`; git-ignorováno. Vestavěný PHP server soubory z `/uploads/` servíruje jako `/static/` (`web/index.php`); **v produkci musí web server servírovat `web/uploads/` na `/uploads/` a adresář musí přežít nasazení** (na rozdíl od `web/static`, který build přepisuje).
- **Formulář hry:** pole „Obrázek“ ukazuje náhled a tlačítka Nahrát/Změnit a Odebrat obrázek; po nahrání se `imageUrl` jen vyplní a uloží se až s hrou. Ruční zadání URL zůstalo v rozbalovací položce „Zadat adresu obrázku“ (staré obálky `/static/covers/…`, externí odkazy).

## Důsledky
- Nahraný soubor, který se nakonec k žádné hře nepřiřadí (nebo po výměně obrázku), zůstává na disku; úklid osiřelých souborů zatím není.
- Staré obálky z prototypu nejsou čtvercové, dokud se ve správě her nenahradí.
- Zálohovat je třeba i `web/uploads/`, ne jen databázi.
