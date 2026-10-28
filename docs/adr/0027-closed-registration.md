# 0027 – Closed registration

Status: accepted (2026-10-23)

## Context
The application so far is used only by a closed party, but the Google login (ADR 0002) has so far created an account for anyone who logged in.

## Decision
- On Google login an **account is not created** if the user is not in the database (by `googleId`, or by the e-mail of an account taken over from the old application) and does not come with a valid invitation. Existing users log in unchanged.
- Invitations (`PartyInvitation`) are links with a token (`/join/<token>`), not bound to an e-mail. The frontend therefore passes the token when a not-logged-in user logs in from an invitation link: `GET /api/auth/google?invitation=<token>` stores it in the session, `googleCallback` reads it after the return from Google (and deletes it from the session) and allows a new account only for an existing, unexpired invitation.
- A rejected new user is redirected to `FRONTEND_URL/?registration=denied` and the login page explains that registration is closed and an invitation is needed.
- `POST /api/auth/dev-login` (only `dev`/`test`) is unchanged.

## Consequences
- An invitation can be used by anyone who has the link (valid for 7 days); restricting it to a specific e-mail would require changing invitations.
- After registering, the new member is added to the party by the usual acceptance of the invitation (`/join/<token>`), which the frontend remembers across the login.
