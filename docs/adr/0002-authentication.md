# 0002 – Authentication

Status: accepted (2026-10-02)

## Context
Specification: login via Google ("Google 2FA" – understood as logging in with a Google account that handles 2FA by itself). Every user must have a nickname.

## Decision
- **Google OAuth 2** via `knpuniversity/oauth2-client-bundle` + `league/oauth2-google`. Flow: `GET /api/auth/google` → Google → `GET /oauth/google/check` → the user is found/created by `googleId` → `Security::login()` → redirect to `FRONTEND_URL`.
- **Stateful session (cookie)**, not JWT: the SPA is on the same domain (in dev via the Vite proxy), the cookie is `SameSite=Lax`, and token management on the client is eliminated. Unauthorized access returns `401` JSON (`JsonEntryPoint`), not a redirect.
- The helper `SessionLoginAuthenticator` never activates itself (`supports()` = false); it only exists so that `Security::login()` has an authenticator.
- The **nickname** is set after the first login (`PATCH /api/me`); the frontend does not let the user proceed without it. The backend does not enforce it on other endpoints yet (see open points).
- **Registration is closed** (ADR 0027): a new account is created only via a valid invitation.
- `POST /api/auth/dev-login` exists **only in `dev`/`test`** (otherwise 404) – it enables development and tests without Google credentials.
- Party authorization via `PartyVoter` (`PARTY_VIEW` = member, `PARTY_ADMIN` = admin).

## Consequences
- CSRF: mutations require a JSON `Content-Type` and the cookie is SameSite=Lax; if the API is ever on a different domain, a CSRF token must be added / switch to SameSite=None + token.
- CORS (`nelmio_cors`) allows credentials only for `CORS_ALLOW_ORIGIN`.
- Open: enforcing the nickname on the backend, rate limiting.
