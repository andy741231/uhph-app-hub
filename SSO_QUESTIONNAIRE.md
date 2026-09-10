# UH SSO Questionnaire — Responses

**Application: UHPH App Hub** (`https://uhph.uh.edu/apps`)
*Completed 2026-09-09 — fill the two `[TBD]` items before sending.*

---

## UH Questions

**• What is the UH Department name and the Business Owner requesting the integration?**

- Department: UHPH
- Business Owner: Andy Chan

**• Who is the UH technical contact working on this project?**

- Andy Chan — mchan3@cougarnet.uh.edu

**• What is the UH branded name to be used for this application?**

- UHPH App Hub

**• When will this application be tentatively launched?**

- The App Hub is already in production (currently using a transitional internal
  login). This request is to replace that local login with institutional SSO.
  Tentative SSO cutover: `[TBD — month/year]`

**• Who will use this SSO? (e.g. admitted students, enrolled students, staff, faculty)**

- Faculty and staff only (Grant Review reviewers/submitters and application
  administrators). No students, no external/guest users.

**• Would you like your application added in AccessUH?**

- No. Users will access the application directly at `https://uhph.uh.edu/apps`.

---

## Vendor Questions

**• Who is the Vendor and what is the vendor's website?**

- No external vendor — the application is developed and maintained in-house by
  UHPH.

**• What is the application name?**

- UHPH App Hub

**• Is this application on premise at UH or hosted in the cloud?**

- On-premise at UH (IIS web server on UH infrastructure), reachable at
  `https://uhph.uh.edu/apps`.

**• Does the vendor require a test account?**

- No dedicated test account required — testing will be done with the technical
  contact's own UH account.

**• Which SSO protocol/version is supported? (Ex. OIDC, SAML.)**

- **OIDC** — Authorization Code flow against the Microsoft Entra ID v2.0
  endpoints, with `client_secret_post` client authentication (same pattern as
  our existing RCMI Tickets integration in this tenant).

**• For OIDC – please provide the URLs to be used.**

- Redirect URI (production, **Web** platform — exact match required):
  `https://uhph.uh.edu/apps/auth/oidc/callback`
- Redirect URI (local development, optional but recommended so we can test
  without touching prod):
  `http://localhost:8000/apps/auth/oidc/callback`
- Endpoints the application consumes (tenant `170bbabd-a2f0-4c90-ad4b-0e8f0f0c4259`):
  - Authorize: `https://login.microsoftonline.com/170bbabd-a2f0-4c90-ad4b-0e8f0f0c4259/oauth2/v2.0/authorize`
  - Token: `https://login.microsoftonline.com/170bbabd-a2f0-4c90-ad4b-0e8f0f0c4259/oauth2/v2.0/token`
  - Userinfo: `https://graph.microsoft.com/oidc/userinfo`
  - JWKS: `https://login.microsoftonline.com/170bbabd-a2f0-4c90-ad4b-0e8f0f0c4259/discovery/v2.0/keys`
  - End session: `https://login.microsoftonline.com/170bbabd-a2f0-4c90-ad4b-0e8f0f0c4259/oauth2/v2.0/logout`

**• For OIDC - What permissions are required?**

- Delegated, standard scopes only: `openid email profile`.
- No Microsoft Graph application permissions and no admin-consent resources;
  identity is read from the ID token / userinfo endpoint.
- Client credential: a **client secret** (not a certificate) — our client
  supports `client_secret_post`/`client_secret_basic` only.

**• What is the login URL?**

- `https://uhph.uh.edu/apps/login`
  (The "Sign in with UH" button on this page redirects to the Entra ID
  authorize endpoint; the post-login landing page is `https://uhph.uh.edu/apps`.)

**• For SAML, will there be a separate Test SAML and Prod SAML setup needed for this app?**

- N/A — OIDC. For testing we would like the local-development redirect URI
  above added to the app registration (or, if policy prefers, a separate dev
  app registration).

**• What are the Attributes/Claims to be used for this integration?**

- `sub` — unique user identifier (stored as the permanent external subject; we
  never key accounts off email)
- `email`
- `name`
- `given_name`, `family_name`
- (Note: this tenant's userinfo endpoint returns `sub, name, given_name,
  family_name, picture, email` — confirmed during the RCMI Tickets
  integration; there is no `preferred_username` claim.)

**• Does the Unique User Identifier need to be set to "persistent"?**

- Yes — the OIDC `sub` claim (stable, non-reassignable) is our unique user
  identifier. Local accounts are bound to `sub`; email is used only as a
  one-time first-login linking aid.

**• Does the SAML Assertion need to be encrypted?**

- N/A — OIDC. All token exchanges occur over HTTPS; ID tokens are signature-
  validated against the tenant JWKS.

**• Please provide the corresponding Test/Prod Metadata that is to be used.**

- N/A for OIDC — the tenant discovery document serves as metadata:
  `https://login.microsoftonline.com/170bbabd-a2f0-4c90-ad4b-0e8f0f0c4259/v2.0/.well-known/openid-configuration`
- On our side, the only metadata Entra needs is the redirect URI(s) listed
  above.

**• Please provide any SSO Documentation.**

- Microsoft Entra ID / Microsoft identity platform OIDC protocol docs:
  `https://learn.microsoft.com/entra/identity-platform/v2-protocols-oidc`
- Internal precedent: the **RCMI Tickets** WordPress site (uhph.uh.edu/rcmi)
  already uses this exact pattern — direct Entra ID app registration, OIDC
  Authorization Code flow, `client_secret_post`, v2 endpoints — and is in
  production. The UHPH App Hub follows the same integration model.
