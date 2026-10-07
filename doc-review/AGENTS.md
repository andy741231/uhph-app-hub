# Document Reviewer — agent notes

Standalone extraction of the legacy Hub Document Reviewer. Do not modify
the legacy app at `/Users/mchan3/CascadeProjects/hub` or the shared
`packages/laravel-app-hub-client` package — changes must stay inside this
directory (plus the Hub's app registration/seeder).

## Commands

```bash
vendor/bin/phpunit        # tests (SQLite in-memory, no .env needed)
vendor/bin/pint --test    # style
npm run build             # frontend build
php artisan flag-words:import <file> [--apply]
```

Local run happens via the repo-root `server.php` (`/apps/doc-review`).

## Architecture invariants

- Hub SSO only: `LoginController` uses the shared `HubClient`; Inertia
  requests get `Inertia::location()` (409 + `X-Inertia-Location`), plain
  browser requests get the external redirect. `HubIdentityService`
  (MapsHubIdentity) and `HubLoginDestination` (DeterminesLoginDestination)
  are bound in `AppServiceProvider`.
- Roles are exactly `admin` / `user`, mirrored from the Hub identity; the
  Hub subject UUID is authoritative and unique. No local passwords, user
  CRUD, or registration routes.
- `App\Http\Middleware\EnsureHubSessionIsFresh` extends the shared
  freshness middleware: it bypasses revalidation for the named `logout`
  route (stale sessions must still reach the signed Hub logout) and rewrites
  `url.intended` to a safe GET destination when an unsafe-method request is
  intercepted. `EnsureUserIsActive` + `DocumentPolicy` enforce owner-or-admin.
- Uploads live on the private `local` disk under `storage/app/private/documents`;
  deletes remove the upload and its PDF preview. Rescan stages a new preview,
  extracts/scans first, commits flag/document changes in a transaction, and
  only then deletes the superseded preview; orphaned staged files are removed.
- `FlagScanner` preserves legacy matching semantics (leading-boundary
  prefix/stem match for single words). Tests must not assert whole-word.
- No `v-html` anywhere for document content — extracted text renders
  escaped; Word docs use the generated PDF preview.
- Session cookie `uhph_doc_review_session` is scoped to
  `/apps/doc-review`; the app fails closed (503 on `/login`) when Hub
  credentials are missing.

## Navigation style

Follow `docs/top-nav-standard.md` (repo root). Implementation lives in
`resources/js/Layouts/DocReviewLayout.vue` using Tailwind tokens and Font
Awesome icons (`@fortawesome/fontawesome-free`, CSS imported once in
`resources/js/app.js`) per the standard's iconography table. Keep it in
sync with the standard — do not edit one app's nav without updating the
other.

## Testing notes

- `tests/TestCase` calls `withoutVite()` — feature tests don't need a
  build.
- Inertia requests in tests need `X-Inertia` plus `X-Inertia-Version`
  matching the build manifest hash — see `HubSsoTest::inertiaHeaders()`.
- `Http::fake()` appends stubs (first match wins); the token-exchange fake
  reads a mutable payload property for multi-exchange tests.
- Local verification helpers (session seeding, browser screenshot pass)
  live under `build-logs/verification/` — ignored, local-only, and they
  hard-refuse to run when `APP_ENV` isn't `local` or the DB isn't SQLite.
