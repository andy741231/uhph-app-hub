# UHPH App Hub Admin & Web Developer Manual

## 1. What UHPH App Hub does

UHPH App Hub is a small Laravel application that lives at `E:\apps\app-hub` but is served from the IIS parent application at `/apps`. It is the front door for every application placed under `/apps`:

- Authenticates users at `/apps/login`.
- Shows each user a dashboard of the applications they are allowed to launch.
- Lets administrators manage users, application registration, and per-application role assignments.
- Provides a lightweight, one-time-code OAuth2-style flow so child applications can confirm a user's identity and role.

## 2. URLs and routing

| URL | What it does |
| --- | --- |
| `/apps` or `/apps/login` | Login page |
| `/apps/dashboard` | User's application launcher |
| `/apps/admin` | Admin index |
| `/apps/admin/users` | Manage users |
| `/apps/admin/applications` | Manage registered applications |
| `/apps/launch/{key}` | Launch an assigned application |
| `/apps/sso/authorize` | Start the mini-OAuth identity hand-off |
| `/apps/sso/token` | Exchange an authorization code for identity |

IIS routing is controlled by `E:\apps\web.config`. Physical directories under `E:\apps\` are served directly, so existing applications keep working. Requests that do not match a real file or directory are rewritten to `E:\apps\index.php`, which loads UHPH App Hub.

> Important: The `app-hub` directory itself is blocked from direct HTTP access by `web.config`.

## 3. Environment essentials

Production must set:

```
APP_URL=https://<host>/apps
SESSION_PATH=/apps
HUB_AUTHORIZATION_CODE_TTL=60
```

- `SESSION_PATH` makes sure the session cookie is scoped to `/apps`.
- `HUB_AUTHORIZATION_CODE_TTL` (default 60 seconds) controls how long the one-time SSO code is valid. The code is hard-capped between 30 and 300 seconds.

## 4. First-time test / temporary login

If the database has been seeded, a test account exists:

- **Email:** `test@example.com`
- **Password:** `password`

This account is intended for local development only.

Every active user may also have an optional local password for `/apps/login` when the login mode allows it (see §10). `users.password = NULL` means the account has no local password; in modes that offer CougarNet those users sign in with CougarNet only. Users can set one later from the **Set up or reset password** link on the login page, and admins can set one on the user edit page. Existing password hashes are preserved as-is — only newly provisioned accounts default to NULL.

For a safe, real administrator account, run the interactive command so the password is not exposed in shell history:

```bash
composer exec --working-dir="E:/apps/app-hub" -- php artisan hub:create-admin
```

## 5. Including and excluding applications

### Register a new application

1. Sign in as an admin and go to **Applications**.
2. Click **Add application**.
3. Fill in the fields:

| Field | Purpose |
| --- | --- |
| `name` | Display name on the dashboard. |
| `invitation_message` | Optional intro added to new-user invitation emails for this app, edited with a rich-text editor (bold, italic, links, lists — stored as Markdown, up to 1,000 characters including markup). |
| `key` | URL-safe identifier, e.g. `grant-review`. Must match `^[a-z0-9]+(?:-[a-z0-9]+)*$`. |
| `path` | Physical path under `/apps`, e.g. `/apps/grant-review`. Must not contain `.` or `..` segments. |
| `callback_url` | Required only for SSO. Must match the path pattern above and must be saved before credentials can be generated. |
| `roles` | Comma-separated list of roles the app supports, e.g. `admin,submitter,reviewer`. Each role starts with a letter and uses only letters, numbers, underscores, or hyphens. Leave blank if the app does not use roles. |
| `enabled` | Whether the app is visible and launchable. |
| `sort_order` | Order on the dashboard. |

### Include an application for users

1. Go to **Users** → edit the user.
2. Check the application and, if the app has roles, select a role from the drop-down.
3. Save.

The application appears on the user's dashboard immediately (provided it is also `enabled`).

### Exclude an application

- **Globally:** Edit the application and uncheck **Enabled**.
- **For one user:** Edit the user and uncheck the application, or leave it enabled but do not select a role.
- **Delete a user from an app:** Uncheck the app in the user's edit page and save.

## 6. Editing user permissions for applications

Permissions are managed through the pivot between users and applications:

- `enabled` in the user edit form controls whether the user can see and launch the app.
- `role` is chosen from the roles defined for that application.

A user can have different roles for different applications. If an application defines no roles, the only option is enable/disable.

Validation rules prevent assigning a role that does not exist for the application, and prevent removing a role from an application while a user still has it assigned.

## 7. Bulk user import

Admins can import users from a CSV at **Users → Import**.

CSV format (must match exactly):

```csv
name,email,application,role
Jane Submitter,jsubmitter@uh.edu,grant-review,submitter
Robert Reviewer,rreviewer@cougarnet.uh.edu,grant-review,reviewer
```

Rules:

- Header must be `name,email,application,role`.
- Maximum 1,000 rows per upload.
- Email must be `@uh.edu`, `@central.uh.edu`, or `@cougarnet.uh.edu`.
- `application` must match an existing and enabled application `key`.
- `role` must be one of the roles defined for that application.

New users are created without a local password (`password = NULL`) and are sent a single invitation email naming every application assigned in the import. Existing users keep their credentials and simply get the new application assignment.

Invitation emails are app-aware:

- **One assigned app:** the subject is `<App name> — set your UHPH App Hub password` in `local` mode (or `<App name> — your account is ready` in `sso`/`hybrid`) and the body names the app. Each app's optional `invitation_message` is included as an intro line.
- **Multiple assigned apps:** the subject is `Set your UHPH App Hub password` (or `Your UHPH App Hub account is ready` in `sso`/`hybrid`), the body lists the app names in alphabetical order, and each custom message is prefixed with `<App name>:`.
- **Self-service setup/reset:** requests made from the login page have no application context and use the generic `Set up or reset your UHPH App Hub password` wording.

In `sso` mode the invitation contains only the CougarNet sign-in action. In `hybrid` mode it leads with CougarNet and also offers an optional link to set up a local Hub password (7-day expiry); the invitee can later use the **Set up or reset password** link on `/apps/login` to create or replace a local password.

## 8. The mini-OAuth / one-time-code flow

UHPH App Hub uses a tiny OAuth2-style code flow. This lets a child application verify that a user is authenticated and what role they have, without sharing a session cookie.

### Sequence

1. The child application sends the browser to:

   ```
   /apps/sso/authorize?client_id=...&redirect_uri=...&state=...
   ```

   - `redirect_uri` must exactly match the `callback_url` saved for the application.
   - `state` must be at least 16 characters.

2. UHPH App Hub validates the user is assigned to the application and the application is enabled.
3. UHPH App Hub creates a single-use authorization code, valid for `HUB_AUTHORIZATION_CODE_TTL` seconds. The code snapshots the Hub session's login method (`sso` or `local`, or null for sessions without a marker).
4. It redirects the browser back to:

   ```
   {callback_url}?code=...&state=...
   ```

5. The child application server-side posts to:

   ```
   /apps/sso/token
   ```

   with:

   - `Authorization: Basic {base64(client_id:client_secret)}`
   - `grant_type=authorization_code`
   - `code=...`
   - `redirect_uri=...`

6. If valid, the token endpoint returns JSON:

   ```json
   {
       "token_type": "hub_identity",
       "subject": "<uuid public_id>",
       "email": "user@uh.edu",
       "name": "User Name",
       "application": "grant-review",
       "role": "submitter",
       "login_mode": "hybrid"
   }
   ```

### Generating client credentials

1. Register the application with a `callback_url` and save.
2. In the application edit form, click **Generate client credentials**.
3. Copy the `client_id` and `client_secret` shown; the secret is displayed only once.
4. Store them in the child application's configuration. The secret hash is stored in UHPH App Hub; only the plain secret should live in the child app.

### Security notes

- HTTPS is required in production for `/sso/authorize` and `/sso/token`.
- Each authorization code can be used exactly once and expires quickly. Token exchange also rejects a code whose snapshotted `login_method` is disallowed by a later `HUB_LOGIN_MODE` change; codes without a snapshotted method (legacy sessions) remain valid until expiration.
- The `redirect_uri` / `callback_url` must match exactly and may not contain path-traversal segments.

## 9. Adding a new Laravel or other application under `/apps`

A web developer can add a new application without touching UHPH App Hub code. The typical steps are:

1. Create the application under `E:\apps\<key>` (for example, `E:\apps\grant-review`). This must be a real directory so IIS's `Preserve physical applications` rule serves it directly.
2. Configure the child app to be served from `/apps/<key>`.
3. In UHPH App Hub, register an application with the same `key` and `path`, e.g. `path = /apps/grant-review`.
4. Assign users to the new application from the **Users** section.

### If the new app does not need SSO

- The user clicks the app tile on the dashboard.
- UHPH App Hub checks that the app is enabled and the user is assigned.
- On success, the browser is redirected to the `path`.
- The child app is responsible for its own session/identity from there.

### If the new app wants UHPH App Hub identity

- Complete the mini-OAuth steps in section 8.
- In the child app, implement the `authorize` redirect and the `token` exchange.
- Use the returned `subject` (a UUID) as the user's stable identifier.

### Important: do not put the new app inside `app-hub`

The `app-hub` directory is hidden from the web. New applications must be siblings of `app-hub` under `E:\apps\`, not inside it. Do not name a new directory `app-hub`.

## 10. Login modes (CougarNet and local passwords)

`HUB_LOGIN_MODE` controls which sign-in methods `/apps/login` offers:

| Value | Sign-in methods | Invitations |
| --- | --- | --- |
| `sso` | CougarNet (Microsoft Entra ID) only | CougarNet sign-in instructions; no password token is created |
| `local` | Hub-local password only | Set-password invitation with a one-time `/apps/set-password/{token}` link |
| `hybrid` | CougarNet plus an optional local password | CougarNet instructions plus an optional local-password setup link (7-day expiry) |

Set `HUB_LOGIN_MODE` in `.env` together with `ENTRA_TENANT_ID`, `ENTRA_CLIENT_ID`, and `ENTRA_CLIENT_SECRET` (required for CougarNet), then run `php artisan config:clear`. When `HUB_LOGIN_MODE` is absent, the legacy `ENTRA_SSO_ENABLED` flag still applies: `true` behaves as `hybrid`, `false` as `local`.

- In modes with CougarNet, `/apps/login` shows "Sign in with CougarNet", which runs an OIDC authorization-code flow against the tenant's v2.0 endpoints and returns to `/apps/auth/oidc/callback`. In `local` mode the `/apps/auth/oidc/*` routes are hidden (404); in `sso` mode `POST /apps/login`, `/apps/forgot-password`, and `/apps/set-password` are hidden (404).
- The Entra `sub` claim is stored in `users.external_subject`. First sign-in links an existing account by **exact** email match — `@cougarnet.uh.edu` and `@central.uh.edu` are different addresses. If a sign-in fails with a link error, an admin can paste the subject from the failed `login_audits` entry into the **SSO subject** field on the user edit page.
- Accounts stay invite-only: unknown UH accounts are denied until an admin imports or creates them.
- In `hybrid`, `/apps/login` presents both choices: "Sign in with CougarNet" and a local password form. Every active user with a configured password may use either path; accounts created through imports or the managed-users API start with `password = NULL` and use CougarNet until they set one. The password column became nullable by migration — existing hashes were preserved untouched.
- The **Set up or reset password** link on `/apps/login` (modes that allow local passwords) emails a one-time `/apps/set-password/{token}` link for active accounts. Its response is always the same generic confirmation, so it cannot reveal whether an address has an account, and disabled accounts can neither request nor redeem links.
- New-user emails follow the mode table above. A single-app invitation uses the subject `<App name> — your account is ready` in `sso`/`hybrid` and `<App name> — set your UHPH App Hub password` in `local`. In `sso`/`hybrid`, the CougarNet sign-in link carries `?application=<key>` so the login page names the app; in `local`, the action is the one-time `/apps/set-password/{token}` link. Multi-app invitations use `Your UHPH App Hub account is ready` (or `Set your UHPH App Hub password` in `local`) and list every assigned app (each with its optional `invitation_message`, prefixed `<App name>:`).
- Child applications are unaffected: they still receive the Hub `public_id` subject and go through `/apps/sso/authorize` in every mode. The identity response also reports `login_mode`; clients such as Grant Review store it in their session and adapt onboarding text and email previews after the next Hub authorization or revalidation — at most `HUB_SESSION_REVALIDATION_MINUTES` unless the user signs out and back in.
- Each successful sign-in tags the Hub session with the method used (`sso` or `local`). If `HUB_LOGIN_MODE` later changes so that method is no longer allowed, the session is signed out on its next protected Hub request or child authorization and the user is asked to sign in again; sessions created before this feature carry no marker and stay valid until normal reauthentication.
- To roll back to password-only sign-in, set `HUB_LOGIN_MODE=local` and clear the config cache; CougarNet sign-in is removed and local-password sign-in becomes the only Hub login method.

## 11. Common admin tasks

| Task | How |
| --- | --- |
| Reset the admin password | Log in as another admin, edit the user, and set a password. |
| Disable a user | Edit the user, set `status = disabled`, save. Their sessions are destroyed. |
| Disable admin for yourself | Not allowed; you cannot disable or delete your own account. |
| Rotate credentials | In the application edit form, click **Generate client credentials**. |
| See launch history | Check the `application_launch_audits` and `login_audits` tables. |

## 12. Useful commands

```bash
# Run migrations
composer exec --working-dir="E:/apps/app-hub" -- php artisan migrate --force

# Create an admin interactively
composer exec --working-dir="E:/apps/app-hub" -- php artisan hub:create-admin

# Verify the install
composer test --working-dir="E:/apps/app-hub"
```
