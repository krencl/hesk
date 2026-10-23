# 0002 – Autentizace

Stav: přijato (2026-10-02)

## Kontext
Specifikace: přihlášení přes Google („Google 2FA“ – chápáno jako přihlášení Google účtem, který si 2FA řeší sám). Každý uživatel musí mít přezdívku.

## Rozhodnutí
- **Google OAuth 2** přes `knpuniversity/oauth2-client-bundle` + `league/oauth2-google`. Tok: `GET /api/auth/google` → Google → `GET /oauth/google/check` → uživatel se najde/založí podle `googleId` → `Security::login()` → redirect na `FRONTEND_URL`.
- **Stavová session (cookie)**, ne JWT: SPA je na stejné doméně (v dev přes Vite proxy), cookie je `SameSite=Lax`, odpadá správa tokenů na klientu. Neautorizovaný přístup vrací `401` JSON (`JsonEntryPoint`), ne redirect.
- Pomocný `SessionLoginAuthenticator` se nikdy sám neaktivuje (`supports()` = false); slouží jen tomu, aby `Security::login()` mělo authenticator.
- **Přezdívka** se nastavuje po prvním přihlášení (`PATCH /api/me`); frontend bez ní nepustí dál. Backend ji zatím nevynucuje na ostatních endpointech (viz otevřené body).
- **Registrace je uzavřená** (ADR 0027): nový účet vznikne jen přes platnou pozvánku.
- `POST /api/auth/dev-login` existuje **jen v `dev`/`test`** (jinak 404) – umožňuje vývoj a testy bez Google credentials.
- Autorizace part přes `PartyVoter` (`PARTY_VIEW` = člen, `PARTY_ADMIN` = admin).

## Důsledky
- CSRF: mutace vyžadují JSON `Content-Type` a cookie je SameSite=Lax; pokud bude API někdy na jiné doméně, je nutné přidat CSRF token / přejít na SameSite=None + token.
- CORS (`nelmio_cors`) povoluje credentials jen pro `CORS_ALLOW_ORIGIN`.
- Otevřené: vynucení přezdívky na backendu, rate limiting.
