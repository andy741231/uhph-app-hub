# UHPH App Hub Project Instructions

## Deployment

- The Laravel source is stored in `E:\apps\app-hub` but is served from the parent IIS application at `/apps`.
- `E:\apps\index.php` is the public front controller bridge.
- `E:\apps\web.config` rewrites only non-physical Hub routes and must continue preserving existing physical application directories.
- The `app-hub` source directory must remain blocked from direct HTTP access.
- The production environment must set `APP_URL` to the full `/apps` URL, `SESSION_COOKIE=uhph_app_hub_session`, and `SESSION_PATH=/apps`. The dedicated cookie name avoids CSRF failures caused by legacy root-path `app-hub-session` cookies.
- Hub static assets are served from physical files at the `E:\apps` root (e.g. `favicon.png`, `favicon.ico`), not from `app-hub/public`, because the bridge rewrites only non-physical requests.
- Regenerate and redeploy the Hub favicon with `php E:/apps/app-hub/scripts/generate-favicon.php` (requires GD); it writes `app-hub/public` and copies to the served `E:\apps` root.

## Verification

Run from any directory:

```bash
composer validate --working-dir="E:/apps/app-hub" --strict
composer check-platform-reqs --working-dir="E:/apps/app-hub"
composer test --working-dir="E:/apps/app-hub"
```

Verify the IIS integration over HTTPS:

- `/apps/` redirects guests to `/apps/login`.
- `/apps/login` returns 200.
- `/apps/up` returns 200.
- `/apps/app-hub/composer.json` returns 404.
- Existing physical applications continue returning their original responses.

## Administration

The production database is MySQL on `uhph-server1.cougarnet.uh.edu` (database `app-hub`), configured via the ignored `.env`. The legacy SQLite file at `database/database.sqlite` is kept only as a backup of pre-migration data; do not switch `DB_CONNECTION` back to `sqlite` in production.

Run migrations and register the default protected applications with:

```bash
composer exec --working-dir="E:/apps/app-hub" -- php artisan migrate --force
composer exec --working-dir="E:/apps/app-hub" -- php artisan db:seed --force
```

Migrate operational data (applications with their SSO credentials, users, role assignments, and audit history) from the legacy SQLite database into the configured MySQL database. Always run with `--dry-run` first:

```bash
php E:/apps/app-hub/scripts/migrate-sqlite-to-mysql.php --dry-run
php E:/apps/app-hub/scripts/migrate-sqlite-to-mysql.php
```

The migration truncates and re-inserts the `applications`, `users`, `application_user`, `login_audits`, and `application_launch_audits` tables, so it is idempotent but destructive — run it only when switching the database backend. It deliberately skips `authorization_codes`, `sessions`, `cache`, and `jobs` (all ephemeral).

Create an administrator interactively so the password is not exposed in shell history:

```bash
composer exec --working-dir="E:/apps/app-hub" -- php artisan hub:create-admin
```

All UHPH App Hub password creation and reset flows require at least 8 characters containing letters and numbers. Completing a valid set-password invitation authenticates the user, regenerates the session, and launches their application automatically when they have exactly one enabled assignment; users with zero or multiple enabled assignments continue to the Hub dashboard.

The dashboard at `/apps/dashboard` renders assigned applications as a mobile-style launcher. Each tile shows the application favicon from `{path}/favicon.ico` when available; otherwise a deterministic gradient tile with the application initials (`Application::iconInitial()`, `iconColorClass()`, `iconUrl()`) is used. Application icon data lives on the model, so adding a real favicon at an application's registered path is all that is needed to override the default tile.

Administrators can batch-create users and application assignments at `/apps/admin/users/import`. Download the example CSV from that page and keep the exact `name,email,application,role` header. Imports accept up to 1,000 institutional-email rows, validate the complete file before writing, preserve existing account credentials and Hub administrator permissions, and send set-password invitations only to newly created users. Grant Review round assignments remain managed within Grant Review.

Application administrators are distinct from global Hub administrators: `users.is_admin` controls `/apps/admin/*`, while `application_user.role=admin` permits scoped management only for that assigned application. SSO identity responses include a short-lived encrypted `actor_token`; a client must present both its application credentials and that actor token to `GET`, `PUT`, or `DELETE /apps/sso/managed-users`. The Hub rechecks the actor's current assignment, exposes only the authenticated application's assignment list, permits only its registered roles, never grants global administrator status, prevents self-demotion/self-revocation, sends UHPH App Hub invitations for new identities, and records changes in `application_admin_audits`. Clients reconcile local profiles from the GET response and archive rather than physically delete historical records. Existing client sessions must reauthenticate after deploying this protocol change.

The one-time `scripts/import-grant-review-users.php` cross-database migration imports missing legacy Grant Review users and assignments into UHPH App Hub without overwriting existing Hub users or sending email. It runs as a dry run by default and requires `--live`; the explicit legacy password exception is accepted only through stdin and is never stored in the script. Existing compatible bcrypt hashes are copied directly. Grant Review application admins remain ordinary Hub users with the `grant-review` application role `admin`; they are not granted global Hub administrator privileges.

Administrators can delete a single user from the user edit page (`DELETE /apps/admin/users/{user}`) or batch-delete up to 1,000 users from the users index (`DELETE /apps/admin/users/bulk`). Deletion cascades application assignments and pending SSO authorization codes, nulls the user reference on retained login/launch audit rows, and clears active sessions. Administrators cannot delete their own account, and the bulk endpoint rejects selections that include the acting administrator. Both endpoints require confirmation prompts in the UI.

## Login modes (Hub authentication)

`HUB_LOGIN_MODE` selects which sign-in methods `/apps/login` offers:

| Value | Sign-in methods | Invitations |
| --- | --- | --- |
| `sso` | CougarNet (Entra OIDC) only | `HubAccessInvitation` CougarNet instructions; no password token is created |
| `local` | Hub-local password only | `SetPasswordInvitation` with the one-time `/apps/set-password/{token}` action |
| `hybrid` | CougarNet plus an optional local password | `HubAccessInvitation` leading with CougarNet plus an optional local-password setup link (expires in 7 days) |

```dotenv
HUB_LOGIN_MODE=hybrid
ENTRA_TENANT_ID=
ENTRA_CLIENT_ID=
ENTRA_CLIENT_SECRET=
ENTRA_VERIFY_TLS=true
# ENTRA_STATE_TTL=300
# ENTRA_JWKS_CACHE_TTL=3600
```

- Backward compatibility: when `HUB_LOGIN_MODE` is absent, the legacy `ENTRA_SSO_ENABLED` flag is consulted — `true` maps to `hybrid`, `false` to `local`. New deployments should set `HUB_LOGIN_MODE` explicitly; an unsupported value fails fast with `InvalidArgumentException`.
- Mode routing is enforced by the `login-mode` middleware (`EnsureLoginModeAllows`): in `sso` mode `POST /apps/login`, `/apps/forgot-password`, and `/apps/set-password` return 404; in `local` mode `/apps/auth/oidc/*` returns 404. `GET /apps/login` stays available in every mode, and `/apps/sso/authorize` works in all three modes because child applications always sign in through the Hub.
- In `sso` and `hybrid` modes the Hub authenticates users through the UH Microsoft Entra ID application registration (OIDC authorization code flow, v2.0 endpoints, `client_secret_post`). Routes: `GET /apps/auth/oidc/redirect` starts the flow; `GET /apps/auth/oidc/callback` (`oidc.callback`) completes it. The Entra-registered redirect URI must equal `route('oidc.callback')` exactly — `https://uhph.uh.edu/apps/auth/oidc/callback` in production, `http://localhost:8000/apps/auth/oidc/callback` locally.
- Scopes are `openid email profile`. ID tokens are signature-validated against the tenant JWKS (cached `ENTRA_JWKS_CACHE_TTL` seconds, retried once on key misses) plus `iss`/`aud`/`exp`/`nonce` checks. State is a single-use SHA-256 hash in the session with `ENTRA_STATE_TTL` seconds.
- Accounts are invite-only. The OIDC `sub` binds to `users.external_subject` on first sign-in via an exact (case-insensitive) email match — UH aliases such as `@cougarnet.uh.edu` vs `@central.uh.edu` do NOT match. Unknown emails are denied and audited (`not_provisioned`, `subject_conflict`, `disabled`); every SSO audit row records the Entra `email`/`sub` attempted. To fix an alias mismatch, an admin either updates the account's email to the Entra-returned address or pastes the audited `sub` into the **SSO subject** field on the user edit page. Once bound, `sub` is authoritative and email is never consulted again.
- Every successful sign-in tags the Hub session with its method (`sso` or `local`) under `hub.login_method_session_key` (`hub_login_method`). If `HUB_LOGIN_MODE` later changes so that method is no longer allowed, the next protected Hub request or child `/apps/sso/authorize` logs the session out, preserves `url.intended`, and runs the same coordinated frontchannel child logout used by normal sign-out (falling back to a direct login redirect when no enabled app registers a `frontchannel_logout_path`) before landing on login with "The available sign-in methods changed." Sessions created before this feature carry no marker and remain valid until normal reauthentication.
- In modes that allow local passwords, `/apps/login` offers the local password form to every active user with a non-null `users.password`. A local password is optional — `users.password === null` is the authoritative not-configured state, and newly provisioned users (CSV import, managed-users API, admin create without a password) start null: in `hybrid` they can sign in with CougarNet before setting a password, while in `local` they must complete the invitation's set-password link first. Failed local attempts against null-password accounts are audited as `local_password_not_set`. The CougarNet button renders only when the client is fully configured (`EntraOidcClient::configured()`): only `hybrid` with incomplete Entra credentials degrades to local-only login, while `sso` with incomplete credentials returns 503.
- `/apps/forgot-password` (modes that allow local passwords) offers self-service local-password setup/reset by email. Responses are deliberately generic so the endpoint cannot enumerate accounts, links are issued only for `status = active` accounts, and disabled accounts can neither request nor consume password links (token redemption rechecks the account is still active).
- `login_audits.method` records `sso` vs `password`.
- Invitations follow the mode: `sso` sends `HubAccessInvitation` directly without creating a password token; `local` sends `SetPasswordInvitation` through the password broker; `hybrid` sends `HubAccessInvitation` through the broker so a fresh token backs the optional setup link. Both notifications are app-aware: a single-app invitation uses subject `<App name> — your account is ready` (or `<App name> — set your UHPH App Hub password` for set-password); the CougarNet action links to `/apps/login?application=<key>` and in hybrid also offers an optional local-password setup link, while set-password keeps its one-time `/apps/set-password/{token}` action. Multi-app invitations use the generic `Your UHPH App Hub account is ready` / `Set your UHPH App Hub password` subject and list the app names in case-insensitive natural order. Each application's optional `invitation_message` (Markdown, max 1,000 chars including markup, editable by global Hub admins through a Toast UI rich-text editor on the application form; raw HTML is escaped and unsafe links stripped by the mail renderer) appears as an intro line, prefixed `<App name>:` when several apps are listed. Generic resets without app context use the self-service "Set up or reset your UHPH App Hub password" wording.
- `url.intended` survives the Entra round trip, so deep links into child-app SSO (`/apps/sso/authorize`) still work.
- Identity responses include `login_mode` with the current Hub mode. Hub SSO clients (e.g. Grant Review) store it in their session and adapt onboarding copy and email previews after the next Hub authorization or revalidation — at most their configured `HUB_SESSION_REVALIDATION_MINUTES`.
- Never commit or log `ENTRA_CLIENT_SECRET` (store the secret Value, not the Secret ID). Rollback to a password-only Hub is `HUB_LOGIN_MODE=local` plus `php artisan config:clear`.

## Transitional SSO

- Browser authorization endpoint: `GET /apps/sso/authorize`.
- Server-to-server exchange endpoint: `POST /apps/sso/token` with HTTP Basic client authentication.
- Signed browser logout endpoint: `GET /apps/sso/logout`; its URL is issued in the identity response and must not be constructed by clients.
- Identity responses include `application_count`, `login_mode`, and `logout_url`. Clients store them in their local session, show “All applications” only when the count is greater than one, and use the signed URL after clearing the local session. The Hub then coordinates immediate browser logout through every enabled application's validated `frontchannel_logout_path`; each client validates the opaque, two-minute, single-use transaction with `POST /apps/sso/logout/continue` before clearing its own session.
- Direct app authorization and coordinated global logout redirect to `/apps/login?application={key}`. The login controller validates the key against an enabled registered application and uses it only for that request's heading; never persist the application label in the session, so a later direct `/apps/login` visit always says UHPH App Hub. The intended post-login destination remains an absolute origin-plus-path URL in the session. Do not store the registered `/apps/...` path alone because Laravel will prefix the Hub's `/apps` `APP_URL` and produce `/apps/apps/...`.
- Callback paths must exactly match the registered internal `/apps/...` path.
- Client secrets are displayed only once when generated or rotated and must never be committed or logged.
- Authorization codes are stored only as SHA-256 hashes, expire after 60 seconds by default, and are single-use. Each newly issued code also snapshots the Hub session's `login_method` (`sso` or `local`, null for sessions without a marker); token exchange rejects the code when a subsequent `HUB_LOGIN_MODE` change disallows the snapshotted method, while legacy null-method codes remain valid until expiration.
- Production authorization and token exchange require HTTPS.

The hourly authorization-code pruning schedule requires Windows Task Scheduler to invoke `php artisan schedule:run` every minute. Pruning can also be run manually:

```bash
composer exec --working-dir="E:/apps/app-hub" -- php artisan model:prune
```
