# App Hub Project Instructions

## Deployment

- The Laravel source is stored in `E:\apps\app-hub` but is served from the parent IIS application at `/apps`.
- `E:\apps\index.php` is the public front controller bridge.
- `E:\apps\web.config` rewrites only non-physical Hub routes and must continue preserving existing physical application directories.
- The `app-hub` source directory must remain blocked from direct HTTP access.
- The production environment must set `APP_URL` to the full `/apps` URL and `SESSION_PATH=/apps`.
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

Run migrations and register the default protected applications with:

```bash
composer exec --working-dir="E:/apps/app-hub" -- php artisan migrate --force
composer exec --working-dir="E:/apps/app-hub" -- php artisan db:seed --force
```

Create an administrator interactively so the password is not exposed in shell history:

```bash
composer exec --working-dir="E:/apps/app-hub" -- php artisan hub:create-admin
```

All App Hub password creation and reset flows require at least 8 characters containing letters and numbers.

The dashboard at `/apps/dashboard` renders assigned applications as a mobile-style launcher. Each tile shows the application favicon from `{path}/favicon.ico` when available; otherwise a deterministic gradient tile with the application initials (`Application::iconInitial()`, `iconColorClass()`, `iconUrl()`) is used. Application icon data lives on the model, so adding a real favicon at an application's registered path is all that is needed to override the default tile.

Administrators can batch-create users and application assignments at `/apps/admin/users/import`. Download the example CSV from that page and keep the exact `name,email,application,role` header. Imports accept up to 1,000 institutional-email rows, validate the complete file before writing, preserve existing account credentials and Hub administrator permissions, and send set-password invitations only to newly created users. Grant Review round assignments remain managed within Grant Review.

Administrators can delete a single user from the user edit page (`DELETE /apps/admin/users/{user}`) or batch-delete up to 1,000 users from the users index (`DELETE /apps/admin/users/bulk`). Deletion cascades application assignments and pending SSO authorization codes, nulls the user reference on retained login/launch audit rows, and clears active sessions. Administrators cannot delete their own account, and the bulk endpoint rejects selections that include the acting administrator. Both endpoints require confirmation prompts in the UI.

## Transitional SSO

- Browser authorization endpoint: `GET /apps/sso/authorize`.
- Server-to-server exchange endpoint: `POST /apps/sso/token` with HTTP Basic client authentication.
- Callback paths must exactly match the registered internal `/apps/...` path.
- Client secrets are displayed only once when generated or rotated and must never be committed or logged.
- Authorization codes are stored only as SHA-256 hashes, expire after 60 seconds by default, and are single-use.
- Production authorization and token exchange require HTTPS.

The hourly authorization-code pruning schedule requires Windows Task Scheduler to invoke `php artisan schedule:run` every minute. Pruning can also be run manually:

```bash
composer exec --working-dir="E:/apps/app-hub" -- php artisan model:prune
```
