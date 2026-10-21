# ADR 0020: Nahrání obrázku hry s oříznutím na čtverec

Stav: přijato (2026-10-21)

## Kontext
Obrázek hry byl jen URL (ADR 0004, 0011) a obálky ze staré aplikace jsou lokální soubory bez uploadu. Ve správě her má jít obrázek nahrát a rovnou vybrat čtvercový výřez, protože všude (seznam, detail, statistiky) se zobrazuje jako čtverec (`object-fit: cover`).

## Rozhodnutí
- **Editor v prohlížeči:** výběr souboru otevře popup `ImageCropPopup` (stejný základ jako `SelectPopup`) s knihovnou `react-easy-crop`: čtvercový výřez, tažení a přiblížení (posuvník, na dotyku štípnutí). Po „Použít“ `cropToBlob` (canvas) vyřízne výřez, zmenší ho na 600 × 600 px a zakóduje jako **WebP** (kvalita 0,85, průhlednost na bílou), takže se přenáší jen desítky kB a orientaci z EXIF řeší prohlížeč. Prohlížeč, který WebP neumí kódovat (canvas pak vrací PNG), ukáže chybu místo nahrání; kódování na serveru jsem nezvolil, protože vyžaduje PHP s GD/WebP, které není všude (a v dev kontejneru není).
- **Upload:** `POST /api/parties/{id}/game-images` (multipart `file`, smí člen party jako ostatní správa her) vrací `{url}`. `CoverStorage` ověří, že jde o **WebP** (podle obsahu, ne přípony), je čtvercový, má 100–2048 px a nejvýš 2 MB (jinak 422), a uloží ho pod `sha1` obsahu (stejný soubor = stejná adresa, žádné duplicity). Server obrázek nepřekódovává.
- **Úložiště:** `web/uploads/covers/<sha1>.webp` (parametr `app.uploads_dir`, v testech `backend/var/test-uploads`), adresa `/uploads/covers/…`; git-ignorováno. Vestavěný PHP server soubory z `/uploads/` servíruje jako `/static/` (`web/index.php`); **v produkci musí web server servírovat `web/uploads/` na `/uploads/` a adresář musí přežít nasazení** (na rozdíl od `web/static`, který build přepisuje).
- **Formulář hry:** pole „Obrázek“ ukazuje náhled a tlačítka Nahrát/Změnit a Odebrat obrázek; po nahrání se `imageUrl` jen vyplní a uloží se až s hrou. Ruční zadání URL **zaniklo**: `imageUrl` smí být jen adresa uloženého obrázku (`/uploads/covers/<sha1>.webp`, `CoverStorage::URL_PATTERN`), jinak API vrátí 422. Interně tedy existuje jediný formát.
- **Migrace starých obálek:** 68 souborů z `/static/covers/` (128 her) se jednorázově převedlo na WebP 512 × 512 (už byly čtvercové, střed obrázku = výřez, který ukazoval `object-fit: cover`) do `web/uploads/covers/` a `game.image_url` se přepsalo; původní soubory v `frontend/public/covers/` zůstaly jako záloha. Převod proběhl v headless Chromiu (canvas → WebP), protože PHP v dev kontejneru nemá GD s WebP/JPEG. **Import ze staré aplikace obálky už nekopíruje** (`--covers-*`, `--no-covers`, `LEGACY_COVERS_DIR` zanikly) a `image_url` nepřepisuje.

## Důsledky
- Nahraný soubor, který se nakonec k žádné hře nepřiřadí (nebo po výměně obrázku), zůstává na disku; úklid osiřelých souborů zatím není.
- Hra bez nahraného obrázku ukazuje zástupný obrázek; po novém importu ze staré aplikace je třeba obrázky nahrát ručně.
- Zálohovat je třeba i `web/uploads/`, ne jen databázi.
