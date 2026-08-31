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

New users are created with a random password and are sent a **Set password** email. Existing users keep their password and simply get the new application assignment.

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
3. UHPH App Hub creates a single-use authorization code, valid for `HUB_AUTHORIZATION_CODE_TTL` seconds.
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
       "role": "submitter"
   }
   ```

### Generating client credentials

1. Register the application with a `callback_url` and save.
2. In the application edit form, click **Generate client credentials**.
3. Copy the `client_id` and `client_secret` shown; the secret is displayed only once.
4. Store them in the child application's configuration. The secret hash is stored in UHPH App Hub; only the plain secret should live in the child app.

### Security notes

- HTTPS is required in production for `/sso/authorize` and `/sso/token`.
- Each authorization code can be used exactly once and expires quickly.
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

## 10. Implementing full SSO in the future

The current flow is intentionally small. To move to a full enterprise SSO later (for example, SAML 2.0 or an OIDC provider), the usual path is:

1. Keep the application registration in UHPH App Hub so launch and role management do not change.
2. Replace or augment the current login page (`/apps/login`) with an external identity provider, storing the returned `external_subject` in the `users` table.
3. Keep `public_id` as the stable identifier passed to child apps, so child apps do not need to change.
4. Continue to enforce application and role assignments in UHPH App Hub after authentication.

Because the child apps already receive a stable `subject` UUID, the underlying authentication source can be swapped without requiring every child app to change.

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
