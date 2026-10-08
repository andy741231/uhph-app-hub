# Local Authentication Setup

How to run the UHPH App Hub + Grant Review authentication stack locally —
both the legacy standalone login and the Hub SSO integration — without touching
production data, secrets, or configuration.

Every app keeps its own ignored `.env` (gitignored per app). The examples below
are **partial templates for a fresh local setup**, not instructions to
overwrite an existing `.env`: inspect every key in your file, remove or
replace any row pointing at a production host, and never copy production
values — credentials, DB hosts, API keys — into a local `.env`. Never point a
local checkout at the production database.

## Why SSO must share one origin

The Hub registers each application's callback as a **relative** `/apps/...`
path (`applications.callback_url`, validated by `isSafeCallback`) and redirects
the browser with `redirect()->away('/apps/grant-review/auth/hub/callback?...')`.
A relative redirect stays on whatever origin served the Hub — so the Hub and
Grant Review must be reachable on the **same** scheme + host + port.

Two `php -S` instances on different ports do not fix this: `server.php` routes
every application on whichever port serves it, but the browser's relative
callback still lands on the Hub's origin — and even if the paths happened to
line up, each single-worker server would block waiting on a same-server HTTP
call during the token exchange.

The same single-origin rule applies to coordinated logout
(`frontchannel_logout_path` is also a relative `/apps/...` path).

## Prerequisites

- PHP 8.5+ with the extensions in each app's `composer.json`
- Composer
- Node/npm — required even without frontend edits: `public/build` (Vite
  manifest + assets) is gitignored, so a fresh clone 500s on every page until
  the bundles exist
- A **local-only** database. SQLite (`DB_CONNECTION=sqlite` with
  `DB_DATABASE` pointing at a local file) is the simplest option; a local MySQL
  instance also works. Never reuse the production MySQL server or data.

## Environment templates

`app-hub/.env` (relevant keys — inspect the whole file, not just this list):

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000/apps
DB_CONNECTION=sqlite
DB_DATABASE=E:/apps/app-hub/database/local.sqlite
DB_URL=
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
SESSION_DRIVER=file
SESSION_COOKIE=uhph_app_hub_session
SESSION_PATH=/apps
SESSION_SECURE_COOKIE=false

HUB_LOGIN_MODE=local
```

`grant-review/.env` (relevant keys — same inspection rule):

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000/apps/grant-review
DB_CONNECTION=sqlite
DB_DATABASE=E:/apps/grant-review/database/local.sqlite
DB_URL=
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
SESSION_DRIVER=file
SESSION_COOKIE=grant_review_session
SESSION_PATH=/apps/grant-review
SESSION_SECURE_COOKIE=false

HUB_SSO_ENABLED=true
HUB_URL=http://localhost:8000/apps
HUB_CLIENT_ID=
HUB_CLIENT_SECRET=
HUB_CALLBACK_URI=/apps/grant-review/auth/hub/callback
HUB_VERIFY_TLS=true
```

- `APP_ENV=local` matters: the Hub's `AppServiceProvider` forces HTTPS URLs in
  every other environment, and `SESSION_SECURE_COOKIE=false` keeps cookies
  working over plain HTTP.
- `DB_URL=` (explicitly empty), `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`,
  and `MAIL_MAILER=log` are set deliberately so an inherited production
  `DB_URL`, Redis/cache host, or SMTP relay can never leak a network call out
  of the local box.
- `MAIL_MAILER=log` means nothing is actually sent: invitation, set-password,
  and reset links are written to `storage/logs/laravel.log` inside each app —
  copy the URLs out of the log to use them.
- `HUB_LOGIN_MODE=local` exercises Hub-local passwords end to end without any
  Entra/CougarNet setup (`sso`/`hybrid` additionally need Entra credentials and
  are only worth configuring if you are specifically testing CougarNet
  sign-in).
- `HUB_URL` points at the **same origin** you serve the stack on — including
  the `/apps` mount — never at production. `HUB_CALLBACK_URI` must match the
  Hub's registered `callback_url` exactly.
- Distinct `SESSION_COOKIE` names and `SESSION_PATH`s keep the two apps'
  sessions from colliding on the shared origin.
- `HUB_SSO_ENABLED=false` is a valid standalone setup, but it only exercises
  the legacy Grant Review login form — it does not test Hub sign-in or
  coordinated logout. Treat it as intentionally separate.

## Fresh-clone setup

Run from the repo root. Artisan commands go through `composer exec
--working-dir` (artisan has no `--working-dir` flag of its own). On
WSL/Linux/macOS replace every `E:/apps` path — including inside
`--working-dir` arguments — with the POSIX equivalent (e.g. `/mnt/e/apps` under
WSL); Linux PHP cannot open `E:/` paths, and that includes the SQLite
`DB_DATABASE` value in each template above.

```bash
# 1. PHP dependencies
composer install --working-dir="E:/apps/app-hub"
composer install --working-dir="E:/apps/grant-review"

# 2. Frontend bundles — public/build is gitignored, so this is required on a
#    fresh clone and again whenever view/Tailwind classes change
npm ci --prefix="E:/apps/app-hub"
npm run build --prefix="E:/apps/app-hub"
npm ci --prefix="E:/apps/grant-review"
npm run build --prefix="E:/apps/grant-review"
```

Create the SQLite files only if they are absent — PHP's SQLite driver will
not create them and `artisan migrate` fails against a missing file. The
parent `database/` directory must exist (it ships with the checkout); the
snippet verifies it inside the loop and never overwrites an existing file.
PowerShell:

```powershell
foreach ($app in 'app-hub', 'grant-review') {
    $dir = "E:/apps/$app/database"
    $db = "$dir/local.sqlite"
    if (-not (Test-Path $dir)) { throw "Missing $dir — is the checkout complete?" }
    if (-not (Test-Path $db)) { New-Item -ItemType File -Path $db | Out-Null }
}
```

On WSL/Linux/macOS: `touch /mnt/e/apps/app-hub/database/local.sqlite` (and the
same for grant-review) — `touch` also never truncates an existing file.

Once the local `.env` files are prepared, clear any config caches **before
running any schema commands**: a checkout that was pulled forward (or copied)
may carry `bootstrap/cache/config.php` built from production values, and
migration would then read the wrong `DB_*`/host settings. Delete nothing by
hand — let artisan rebuild:

```bash
# 3. Ensure no cached (possibly production) config survives into migrations
composer exec --working-dir="E:/apps/app-hub" -- php artisan config:clear
composer exec --working-dir="E:/apps/grant-review" -- php artisan config:clear

# 4. App keys — only on a FRESH .env that has no APP_KEY yet. Never re-run
#    key:generate after a pull or on an existing file: it overwrites the key.
composer exec --working-dir="E:/apps/app-hub" -- php artisan key:generate
composer exec --working-dir="E:/apps/grant-review" -- php artisan key:generate

# 5. Schema + seed — only after confirming APP_ENV=local and the DB_* keys
#    point at your local file. --force just skips the env prompt; it still
#    writes, so verify first.
composer exec --working-dir="E:/apps/app-hub" -- php artisan migrate --force
composer exec --working-dir="E:/apps/grant-review" -- php artisan migrate --force
composer exec --working-dir="E:/apps/app-hub" -- php artisan db:seed --force
```

Then create a Hub admin and register the local Grant Review credentials:

```bash
composer exec --working-dir="E:/apps/app-hub" -- php artisan hub:create-admin
```

1. Sign in to the Hub at `http://localhost:8000/apps/login`.
2. The seeder registers `grant-review` (enabled, with callback and
   frontchannel logout path already correct for this setup), `flipbook`
   (**enabled** with a frontchannel logout path), and `doc-review`
   (disabled). The seeded Flipbook endpoint only works if Flipbook's own Hub
   integration is configured locally — until it is, **disable Flipbook in the
   local Hub admin** (`/apps/admin/applications`), or coordinated global
   logout will dead-end at `/apps/flipbook/auth/hub-logout.php`. Rule of
   thumb: only applications whose frontchannel endpoint actually works in
   this environment may stay enabled. Do not edit Flipbook's files to make it
   work — just disable it or configure it separately. If you do configure
   Flipbook with Hub credentials, its dashboard tile launches
   `/apps/flipbook/auth/login.php` rather than the public gallery root — that
   is deliberate: the gallery is public and anonymous visitors only see
   "Sign In", so the tile must send Hub users to the login itself. Grant
   Review also distinguishes access from onboarding state: Hub-assigned
   users whose `onboarding_pending` is true (no Hub password, sign-in, or
   CougarNet subject yet) appear as "Invited" in the admin until they
   actually sign in — matching what you should see locally after CSV import
   or single invites.
3. Open `grant-review` in the application editor and generate credentials;
   the client secret is shown once. Paste the ID and secret into
   `grant-review/.env` as `HUB_CLIENT_ID` / `HUB_CLIENT_SECRET`.
4. Assign your test account a Grant Review role (`admin`, `submitter`, or
   `reviewer`) — SSO sign-in without an assignment fails.
5. Clear config caches after any `.env` change:

   ```bash
   composer exec --working-dir="E:/apps/app-hub" -- php artisan config:clear
   composer exec --working-dir="E:/apps/grant-review" -- php artisan config:clear
   ```

## Serving the stack

### WSL, Linux, or macOS — `php -S` with workers (recommended)

```bash
cd /mnt/e/apps   # Linux/macOS: the repo path, e.g. ~/apps
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 server.php
```

`server.php` emulates the production `/apps` mount on one origin, and
`PHP_CLI_SERVER_WORKERS=4` gives the server extra workers so Grant Review's
server-to-server token exchange back to the Hub cannot deadlock.

### Windows IIS — single localhost origin, multi-worker PHP

`PHP_CLI_SERVER_WORKERS` is not supported by Windows PHP, and a single-worker
`php -S` deadlocks during the SSO callback (Grant Review blocks waiting for an
HTTP response from the same busy server). On native Windows, configure a local
IIS site that mirrors the production bindings:

- Site bound to `http://localhost:8000`, physical root `E:\apps`
- `/apps` application → `E:\apps` (bridge `index.php` + `web.config`, which
  rewrites non-physical routes to the Hub front controller)
- Child application `/apps/grant-review` → `E:\apps\grant-review\public`
- PHP via FastCGI with `maxInstances` greater than 1 so concurrent
  same-origin requests (the token exchange) can be served
- `app-hub` source directory blocked from direct HTTP access, as in
  production
- `.env` templates above already match `http://localhost:8000` over HTTP

### Native Windows `php -S` — standalone login only

```bash
php -S localhost:8000 server.php
```

Without workers this supports only the standalone configuration
(`HUB_SSO_ENABLED=false`, legacy local login form). Do not expect the Hub SSO
round trip to complete — the token exchange will hang.

## Keeping a local checkout current

1. `git pull` — only when you are authorized to move the checkout forward.
2. `composer install --working-dir="E:/apps/app-hub"` and the same for
   `grant-review` when `composer.lock` changed.
3. Re-verify `APP_ENV=local` and the `DB_*` keys still point at your local
   file, **then** review and run new migrations locally — never blindly.
4. Clear config caches (`config:clear` commands above) after `.env` or
   config changes.
5. `npm run build` for an app when pulled views introduce new Tailwind
   classes.
6. Preserve your `.env`, `APP_KEY`, and registered Hub credentials — never
   re-run `key:generate` or re-seed to "fix" drift; `db:seed` upserts
   applications but re-seeding without reviewing it can re-enable apps or
   assignments you changed locally.

A pull also preserves your local identities and passwords: your Hub users and
Grant Review profiles live in your own ignored SQLite/MySQL files and are
never synced from production. In particular, never copy production migration
artifacts into a local checkout — the `app-hub/scripts/reconcile-*` scope
files, checkpoints, and hash backups under `storage/logs/auth-cutover-*`
are production-only, restricted, and intentionally absent from Git. The
reconcile script is a reviewed one-time production batch (dry-run by
default, external scope + private checkpoint, `--live` plus an explicit
admin-replacement approval flag), not a local tool — never run it locally
and never automate it; it aborts on changed state or a reused checkpoint
anyway.

## Acceptance check

In a browser:

1. `http://localhost:8000/apps/grant-review` → redirected to the Hub sign-in.
2. Sign in with a Hub-local account that has a Grant Review assignment →
   lands on the Grant Review dashboard.
3. Sign out → browser passes through each enabled registered frontchannel
   endpoint and returns to the Hub sign-in page; both sessions are cleared.

## Local tests on Windows

Feature tests run isolated (`APP_ENV=testing`, `DB_CONNECTION=sqlite`
`DB_DATABASE=:memory:`, empty `DB_URL`, array cache/session/mail, sync queue)
via `vendor/bin/phpunit` — never a Composer test wrapper that clears caches.
A pull never changes your `.env`, so local flags like `HUB_SSO_ENABLED`
stay exactly as you set them; no production flag is ever inherited.

`tests/Feature/EntraOidcTest.php` generates RSA keys and fails on Windows
with `openssl_pkey_export(): Cannot get key from parameter 1` because PHP
ships without a default OpenSSL config. Set `OPENSSL_CONF` on the **test
command only** — never in `.env` or production runtime:

```bash
OPENSSL_CONF='C:\php\extras\ssl\openssl.cnf' php vendor/bin/phpunit tests/Feature/EntraOidcTest.php
```

(Use the `openssl.cnf` bundled under your PHP install's `extras\ssl\`, or a
minimal temp config outside the repo.) The same suite additionally needs
`firebase/php-jwt` present in `vendor/` — if it is missing, run a normal
`composer install` for your local checkout; do not hand-patch vendor.

## Production-only scripts

Two `app-hub/scripts/` tools exist for the completed 2026-10-07 cutover and
must never run from a local checkout or be automated:

- `verify-grant-review-cutover.php` makes **real network calls** against the
  production deployment — including a real anonymous global-logout
  transaction. Run it only with explicit approval; its output is a
  guest/anonymous smoke check, not proof of authenticated behavior.
- `send-grant-review-invitations.php` defaults to a **dry run** and requires
  an externally supplied provisioning scope plus receipt files;
  `--send` actually sends mail. Already SMTP-accepted receipts prevent
  duplicate sends; an ambiguous state stops rather than guessing. Scope and
  receipt files stay in restricted ignored storage — never in Git.

## What local testing does not prove

- Local testing does not validate production. The approved production cutover
  now runs Grant Review with `HUB_SSO_ENABLED=true`; local deployments still
  keep their own settings and must be tested independently.
- Local Hub passwords, Entra/CougarNet, and Grant Review legacy hashes are
  independent stores — never try to "sync" passwords to fix a sign-in problem.
