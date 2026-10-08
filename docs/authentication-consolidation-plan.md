# Authentication Consolidation Plan

Status: preparatory code, the approved one-time credential batch, invitee
provisioning, and the production integration switch are complete
(2026-10-07); Grant Review production runs `HUB_SSO_ENABLED=true`. This
document records intent and status, not approval.

## Implementation Progress

- Preparatory code now preserves Grant Review hashes during identity mapping.
- Integrated Grant Review reset/change/confirmation submissions are blocked;
  reset entry pages direct users to Hub, and the profile links to Hub management.
- Hub now provides `/apps/account/password` with current-password validation,
  throttling, remember-token rotation, and outstanding-reset-token invalidation.
- Local setup and post-pull instructions are in `local-authentication-setup.md`.
- Central onboarding metadata separates access from invitation state: the
  Hub's managed-users responses expose optional `onboarding_pending` (null
  password + no login + no external subject), and Grant Review keeps such
  profiles `invited` until real sign-in/setup — fixing emailed invitees
  wrongly showing Active while their Hub access stays `active` throughout.
- A credentialed Flipbook tile launches `/apps/flipbook/auth/login.php` so
  Hub users reach authentication instead of the public gallery's anonymous
  "Sign In"; unconfigured Flipbook keeps its legacy root.
- The last full Grant Review suite before the onboarding/launcher follow-up
  passed (184 tests, 812 assertions). The follow-up's targeted suites pass:
  Grant Review 53 tests/232 assertions; Hub 27 tests/98 assertions.
  Hub password and login-mode targeted tests also pass; the earlier fixture drift
  (default-assignment duplicate pivots, stale seeder role expectation) is
  repaired, and RSA key generation works under a test-only `OPENSSL_CONF`.
  The former `EntraOidcTest` gap is resolved: the locked `firebase/php-jwt`
  v7.1.0 package was installed under an approved one-time network allowance —
  the full Hub targeted suite (Entra OIDC, managed users, registration,
  password, login mode, launch, identity) now passes 72 tests, 339
  assertions. Still **not** verified: an authenticated browser sign-in and
  coordinated logout round trip with a real user account, and a fresh-clone
  local setup exercised end to end.

### Read-only audit (completed 2026-10-07)

- Approved read-only production inventory completed on 2026-10-07:
  11 Grant Review profiles; 5 active submitters missing Hub identities with
  bcrypt-format legacy hashes; 3 invited submitters with no password or Hub
  identity; 2 email-matched admins with hashes in both stores; 1 admin matched
  by Hub subject but with different email addresses.
- All 3 existing admins have Hub passwords. Unequal salted hashes do not
  establish whether the plaintext passwords differ; preserve Hub passwords
  unless an account-specific replacement is explicitly approved.
- Hub had 3 Grant Review assignments, all admins. Its registration matches
  Grant Review's configured credentials and callback.
- User-level audit snapshots are in ignored `app-hub/storage/logs`, not
  documentation or Git.

### Approved live migration (completed 2026-10-07)

The audit was followed by an explicitly approved, separately authorized
one-time credential batch (`app-hub/scripts/reconcile-grant-review-passwords.php`,
executed with user approval against production MySQL):

- 5 new active submitter identities imported into Hub, each with only the
  Grant Review `submitter` assignment.
- All 3 existing admins' Hub password hashes replaced with the exact Grant
  Review bcrypt hashes under the explicit `--approve-admin-password-replacement`
  approval.
- Hub identity ID33's email renamed `@central.uh.edu` to `@cougarnet.uh.edu`;
  its Hub subject and assignments were left unchanged.
- 8 exact paired hashes verified post-commit; Hub now holds 8 Grant Review
  assignments.
- All 11 Grant Review profiles remain unchanged — no hash restoration, no
  password synchronization. The 3 invited submitters still have no Hub
  identity or Hub password.
- No emails or notifications were sent. Production `HUB_SSO_ENABLED` remains
  `false`; no commit or push is part of this step.
- Restricted before/after backups, the approved scope, and result summaries
  live under ignored `app-hub/storage/logs/auth-cutover-20261007` — never in
  Git or documentation.

### Approved invitee provisioning (completed 2026-10-07)

After user approval of Hub onboarding and invitation email for the 3 invited
submitters, the reviewed one-time batch
`app-hub/scripts/provision-grant-review-invitees.php` created their Hub
identities — IDs 55 (`cliu71`), 56 (`jzhu31`), and 57 (`tmgauss`). Each is
active, has a null password, is not a global Hub administrator, and holds
only the Grant Review `submitter` assignment.

- The original Grant Review profiles and round data are untouched; the 3
  source profiles remain in their invited state.
- Post-provisioning snapshot: 11 Grant Review profiles, 11 Hub Grant Review
  assignments, 8 exact paired password hashes, 3 identities pending password
  setup.
- No emails were sent — deliberately held (below). `HUB_SSO_ENABLED` remains
  `false`.

### Production cutover and guest-path smoke check (completed 2026-10-07)

- Grant Review `HUB_SSO_ENABLED=true`; Hub repaired to `APP_ENV=production`,
  `APP_DEBUG=false`, `HUB_LOGIN_MODE=local` (unchanged).
- Guest-path verification over live HTTPS: Grant Review `/login` 302 → Hub
  `/sso/authorize` 302 → Hub `/login` 200; a malformed callback returns 400;
  an invalid logout request returns 400 (not 404); a real Basic-auth token
  exchange with an invalid code returns 400 `invalid_grant`, proving the
  registered credentials are accepted.
- Anonymous logout chain: a real Hub-issued signed `/apps/sso/logout` 302 →
  Grant Review `/auth/hub/logout` 302 → Flipbook `auth/hub-logout.php` 302 →
  Document Reviewer `/auth/hub/logout` 302 → Hub `/login` 200. This shows the
  frontchannel chain traverses all three registered endpoints; it does not
  prove authenticated sessions clear (no user session existed).
- The 3 approved invitations were sent via the existing `InvitationSender`:
  SMTP accepted `cliu71`, `jzhu31`, `tmgauss` at 2026-10-07T23:28:38Z — SMTP
  acceptance is not inbox-delivery proof, and the private receipt files
  contain no secret links.
- Data snapshot at last verification (not recomputed): 11 Grant Review
  profiles, 11 Hub Grant Review assignments, 8 exact paired password hashes,
  3 identities pending password setup — actual pending count may drop as
  invitees complete set-password.
- Lead-owned evidence is frozen in the private ignored
  `app-hub/storage/logs/auth-cutover-20261007` directory.

### Still pending

- An authenticated browser sign-in and logout round trip with a real user
  account has **not** been verified — the smoke check above was guest and
  anonymous only. Do not claim full rollout verification until that passes.
- A fresh-clone local setup has not been re-verified end to end after these
  changes; `local-authentication-setup.md` documents the intended path.

## Target Design

- App Hub is the only normal identity and password authority.
- Grant Review, Flipbook, and Document Reviewer delegate normal login to Hub.
- Hub `HUB_LOGIN_MODE=local|hybrid|sso` controls password versus CougarNet
  availability; it does not disconnect child applications from Hub.
- A CougarNet password belongs to the external identity provider. It cannot be
  imported into or synchronized with the Hub-local password.
- Production Grant Review uses `HUB_SSO_ENABLED=true` even when Hub login mode
  is `local`. Standalone local-development mode is explicitly separate, not an
  alternative production credential store.
- All normal password setup, reset, and change operations happen in Hub.
- Normal logout remains a validated, coordinated Hub transaction. No unsigned
  redirects, token-validation bypasses, or password checks in logout.

## Findings Before Preparatory Changes

- Production Grant Review reports `hub.enabled=false`, uncached configuration,
  and the production Hub URL and callback path. Client credential fields are
  populated, but their validity has not been checked against Hub.
- Grant Review's legacy login authenticates against its own password hashes.
- The registered front-channel logout handler returns 404 when Hub integration
  is disabled, interrupting coordinated logout.
- Hub identity mapping clears non-admin Grant Review password hashes. Changing
  the flag alone does not clear them, but login and reconciliation can.
- Grant Review's local reset and password-change routes remain present.
- The existing `app-hub/scripts/import-grant-review-users.php` only imports
  missing Hub users; it does not reconcile passwords for existing Hub users.
  It contains a historical account-specific password exception and is not the
  general-purpose cutover tool.

## Phase 1: Read-Only Inventory

Before any database change, inspect schemas and prepare an explicitly reviewed
read-only reconciliation query against the configured production sources.

Classify accounts by normalized email and Hub subject:

1. Missing Hub identity, with a compatible Grant Review password hash.
2. Existing Hub identity with no password, with a compatible legacy hash.
3. Existing Hub password and legacy password hash present.
4. No usable legacy password, including already-mapped non-admin profiles.
5. Conflicting identities, duplicate emails, disabled/revoked accounts, or
   missing/mismatched Grant Review assignments.

Do not output passwords, password hashes, reset tokens, or client secrets.
Exact hash equality is evidence of a copied hash; unequal salted hashes do not
prove different plaintext passwords. Do not claim equivalence without evidence.
Check hashing algorithm compatibility and identity/assignment consistency.

The CLI-only `app-hub/scripts/grant-review-auth-inventory.php` implements the
reviewed MySQL audit on the configured same-server schemas. With no flags it
inspects schema metadata; `--accounts` returns classification flags and
assignment coverage; `--subject-review` examines email differences for shared
Hub subjects. It uses a read-only transaction and never exports hashes or
tokens. It deliberately stops for unsupported connection topology or schemas;
it is not a migrator or a local SQLite setup command.

## Phase 2: Credential Migration Policy

- Missing Hub identity: import the compatible legacy hash unchanged, with
  reviewed identity and Grant Review assignment mapping.
- Existing active Hub identity with no password: seed a compatible legacy hash
  only after checking identity ownership and explicit approval.
- Existing Hub password: preserve it by default. Never blindly replace it.
- Conflicting working credentials require a decision per account. Prefer
  retaining the current Hub password and communicating that it now covers all
  apps. If preserving a Grant Review password instead is explicitly chosen,
  warn that the old Hub password stops working across all apps.
- Never migrate CougarNet passwords, grant global Hub admin from an app role,
  reactivate disabled accounts, or recreate deliberately revoked access.
- No usable hash: use a normal Hub password setup flow, with user consent and
  separately authorized notifications.

If the business requires both old passwords to work temporarily for conflicting
accounts, that is a separate, bounded migration feature: reviewed secondary
legacy hash storage in Hub, shared throttling and auditing, explicit expiry,
and removal on password reset/change. It is not the default recommendation
because it temporarily violates the single-password goal and adds complexity.

The migration must be dry-run by default, transactional for approved writes,
idempotent, and conditional on the target state not having changed since review.
Recheck immediately before writes to avoid overwriting a concurrent Hub reset.
Backups containing hashes must be restricted and never committed. Live writes
and any credential replacement require separate approval.

## Phase 3: Authentication Code Simplification

- Keep production child-app login delegation enabled independently of Hub's
  local/CougarNet mode. Do not force production values into shared defaults.
- Redirect Grant Review forgot-password and password-setup entry points to
  trusted Hub routes while integrated. Reject obsolete local reset submissions
  without mutating local credentials; explain how to obtain a new Hub link.
- Remove or replace the local profile password-change form while integrated.
  Provide a Hub-owned authenticated password-change flow if one is not already
  available; child applications never write the normal password.
- Stop clearing existing Grant Review hashes during identity mapping for the
  transition, so approved rollback does not unexpectedly lose more credentials.
  Retained hashes are inert during normal integrated login, not synchronized.
- Keep IP-restricted emergency admin authentication an explicit break-glass
  exception; its independent credentials are not the user's normal password.
- Do not silently bypass failed Hub validation or fall back to local auth on
  Hub outages. Keep login and logout failures visible and diagnosable.
- Preserve local standalone testing deliberately and document that it does not
  demonstrate production single-password or global-logout behavior.

## Phase 4: Tests and Cutover

Tests use isolated databases and mocked Hub responses, never production data:

- Migration cases for each inventory category, duplicate/subject conflicts,
  disabled accounts, missing assignments, idempotency, and concurrent resets.
- Hub-local login followed by Grant Review callback maps the same profile and
  preserves submissions/reviews; hybrid login maps the same identity.
- Identity mapping does not remove retained legacy hashes during transition.
- Integrated Grant Review local login/reset/change submissions cannot mutate
  local credentials; standalone local mode remains intentionally functional.
- Global logout clears participating sessions only after valid token checks;
  invalid, expired, or replayed tokens fail safely.
- Hub password reset/change establishes one credential across all apps and
  retires any approved transitional credential.

Deployment order:

1. Approve migration policy and inventory; take restricted backups.
2. Deploy tested compatible code; run only reviewed migrations after approval.
3. Apply the approved dry-run-reviewed credential reconciliation.
4. Verify registration, assignments, callback, TLS, and cookie scope.
5. Enable production Grant Review Hub integration and clear its config cache.
6. Pilot fresh browser login with an admin and representative reviewer/submitter;
   verify all three apps and a fresh coordinated logout transaction.
7. Roll out with a brief user notice and monitor endpoint-specific failures.

Do not promise zero user impact until conflicting credentials and missing hashes
are resolved. A user with two different existing passwords cannot keep both
indefinitely while the system also has exactly one password.

## Environment and Rollback Boundaries

Git carries code, tests, and docs; each deployment preserves its ignored `.env`,
credentials, config cache, and built assets. Build frontend assets when template
classes or JS/CSS change; environment-only changes do not require npm builds.

Do not treat switching `HUB_SSO_ENABLED=false` as a complete rollback: it can
break the registered logout chain and mapped profiles may already lack hashes.
An approved rollback must coordinate Hub registration/logout behavior, assess
legacy credential availability, and avoid reverting newer Hub passwords.
No production secrets, identities, or password snapshots go to local development.
