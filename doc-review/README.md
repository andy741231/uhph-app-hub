# Document Reviewer (`doc-review`)

Standalone Laravel 12 / Inertia / Vue 3 application extracted from the legacy
Hub Document Reviewer. Mounted at `/apps/doc-review` (Hub application key
`doc-review`, roles `admin` and `user`).

- **Authentication:** Hub SSO only — no local password login, no local user
  administration. Access, roles, and disablement are managed in the Hub.
- **Documents:** upload `.txt`/`.pdf`/`.docx`/`.doc` (max 10 MB), flag-word
  scanning with per-page counts and suggested replacements, PDF.js preview,
  rescan, download, delete. Owners see their own documents; `admin` can see
  and manage all documents. Flag-word CRUD and bulk deletion are admin-only.
- **Files:** stored on the private local disk under
  `storage/app/private/documents`, never under `public/`. Deleting a
  document removes the upload and any generated PDF preview.
- **Routes:** legacy `docs.*` route names are preserved; app root is the
  document index (`GET /`).

## Requirements

- PHP 8.5+ — `composer check-platform-reqs` lists the exact extension set;
  notable ones: `zip` (`.docx` extraction), `xml`/`xmlwriter`, `fileinfo`,
  `gd`, `mbstring`, `openssl`, `dom`, `zlib`
- Node.js 20.19+ or 22.12+ (required by Vite 7)
- Composer 2
- Optional: LibreOffice (`soffice` on PATH or a standard install location)
  for `.docx`/`.doc` → PDF preview. Without it, `.doc` extraction fails
  safely and `.docx` falls back to plain-text pages.

## Local development

From the repo root (emulates the production `/apps` IIS mount):

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 server.php
```

Then http://localhost:8000/apps/doc-review — unconfigured SSO fails closed
with a 503 on `/login`; once Hub credentials exist the app redirects to the
Hub authorize endpoint.

First-time setup (run from the repo root):

```bash
composer install --working-dir=doc-review
npm install --prefix=doc-review
cp doc-review/.env.example doc-review/.env
composer exec --working-dir=doc-review -- php artisan key:generate
touch doc-review/database/database.sqlite   # default local DB
composer exec --working-dir=doc-review -- php artisan migrate
npm run build --prefix=doc-review
```

## Tests and checks

```bash
composer exec --working-dir=doc-review -- phpunit
composer exec --working-dir=doc-review -- pint --test
npm run build --prefix=doc-review
```

Feature tests do not require a built manifest (`withoutVite()`), but the
built assets are required for real browser traffic.

## Flag-word import

The only data carried over from the legacy app is flag words plus suggested
replacements — never users, documents, uploads, or history.

```bash
# 1. On the legacy host, after verifying the legacy root path:
php scripts/export-legacy-flag-words.php E:/hub > flag-words.json

# 2. On the new app, always dry-run first, then apply (back up first):
composer exec --working-dir=doc-review -- php artisan flag-words:import flag-words.json
composer exec --working-dir=doc-review -- php artisan flag-words:import flag-words.json --apply
```

The importer validates the whole file before writing, rejects malformed
JSON, empty words, and case-insensitive duplicate source/target words, and
rolls back entirely on failure.

## Production deployment

1. **Dedicated database.** Provision a database used only by Document
   Reviewer — never share the legacy Hub schema or another app's database.
   Set `DB_*` accordingly and run `php artisan migrate --force`.
2. **Application key.** Generate a unique `APP_KEY` once and preserve the
   `.env` across deployments — rotating it invalidates sessions and
   encrypted payloads.
3. **Frontend assets.** `npm ci && npm run build`; deploy `public/build/`.
   Requires Node 20.19+/22.12+ (Vite 7).
4. **Environment** (all required):

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://uhph.uh.edu/apps/doc-review
   # Mount-aware asset base — required at build time so Vite-emitted URLs
   # (e.g. the PDF.js worker .mjs) resolve under the app mount:
   ASSET_URL=/apps/doc-review
   HUB_SSO_ENABLED=true
   HUB_URL=https://uhph.uh.edu/apps
   HUB_CLIENT_ID=<issued by Hub admin UI>
   HUB_CLIENT_SECRET=<issued by Hub admin UI; never committed or logged>
   HUB_CALLBACK_URI=/apps/doc-review/auth/hub/callback
   HUB_VERIFY_TLS=true
   SESSION_COOKIE=uhph_doc_review_session
   SESSION_PATH=/apps/doc-review
   SESSION_SECURE_COOKIE=true
   ```

   After any `.env` edit: `php artisan config:clear` (and
   `php artisan config:cache` if the deployment caches config).
5. **Hub registration.** The app is seeded as `doc-review` with
   `enabled=false`. Issue unique client credentials through the existing
   Hub admin UI. Re-seeding is idempotent — it never re-disables the app
   or clobbers credentials.

   Access policy: existing Hub users are assigned `admin`/`user` roles
   manually through the Hub admin UI — no bulk import. Newly created Hub
   users automatically receive a default `doc-review` assignment at
   account creation (`admin` only when the account is a global Hub admin
   at creation time, otherwise `user`). This default runs once per
   account creation; later Hub edits override the role freely and a
   revoked assignment is never re-added, so it cannot resurrect revoked
   access or silently promote anyone.
6. **IIS.** Map the child application only to the public directory
   (likely `E:\apps\doc-review\public`). Source directories must never be
   web-exposed. `public/web.config` already declares the rewrite rules and
   `<remove fileExtension=".mjs" />` + the PDF.js MIME mapping — keep that
   pairing so an inherited `.mjs` mapping cannot trigger IIS 500.19.
7. **Storage permissions.** Grant the app-pool identity write access to
   `storage/` and `bootstrap/cache/`, plus access to the LibreOffice
   executable if Word previews are required.
8. **LibreOffice.** Required for `.docx`/`.doc` → PDF preview and for
   extracting legacy `.doc` files (`.doc` extraction fails safely without
   it and marks the document failed). Each conversion uses a unique
   temporary profile under `storage/app/lo_temp`, and a timed-out
   conversion kills only its own process tree (`taskkill /PID … /T /F`).
9. **Enable last.** Keep `doc-review` disabled in the Hub until
   credentials and configuration are verified, then enable it for
   acceptance testing.
10. **Legacy redirects.** Only after acceptance, add a bounded redirect in
    the legacy site for `GET`/`HEAD` requests matching
    `/hub/docs(?:/.*)?$` → `/apps/doc-review`. Do not pattern-match
    `/hub/docs*` broadly (it would also catch `/hub/docstore` etc.), do
    not redirect unsafe methods, do not modify the legacy `/hub` source,
    and do not add a global `/hub` rewrite.

## Limitations

- `.doc` (legacy Word) cannot be extracted without LibreOffice; the
  document is marked failed with a safe, generic error.
- Flag matching intentionally preserves legacy semantics: single words
  match stems/prefixes at a leading word boundary (e.g. `divers` matches
  `diversity`); multi-word phrases match literally.
- Extracted/converted text is rendered escaped — document-supplied HTML is
  never injected.

## Rollback

1. Remove the legacy `/hub/docs` redirect rule (if it was added).
2. Disable the `doc-review` application in the Hub.
3. Remove the IIS child application mapping.
4. The legacy `/hub` module is untouched, so no changes there are needed.
   Remove the doc-review database and deployment directory only after
   confirming no data must be retained.
