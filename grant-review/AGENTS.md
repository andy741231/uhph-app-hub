# Grant Review Project Instructions

## Deployment

- Grant Review is an IIS child application at `/apps/grant-review` mapped to `E:\apps\grant-review\public`.
- Keep `APP_URL` set to the full public `/apps/grant-review` URL in production.
- Tests override `APP_URL` with `http://localhost` so route tests are independent of the IIS subpath.
- Regenerate the Grant Review favicon with `php E:/apps/grant-review/scripts/generate-favicon.php` (requires GD); it rasterizes the heroicons v2 outline `trophy` logo (the mark shown in every page header) white on the red rounded square and writes `public/favicon.png` + `public/favicon.ico` (replacing the old 0-byte scaffold `favicon.ico`). The `scripts/` directory lives outside `public/`, so it is never web-accessible.

## UHPH App Hub SSO

Hub SSO is active and configured in Grant Review's ignored `.env`:

```dotenv
HUB_SSO_ENABLED=true
HUB_URL=https://uhph.uh.edu/apps
HUB_CLIENT_ID=
HUB_CLIENT_SECRET=
HUB_CALLBACK_URI=/apps/grant-review/auth/hub/callback
HUB_VERIFY_TLS=true
HUB_SESSION_REVALIDATION_MINUTES=15
EMERGENCY_LOGIN_ENABLED=false
EMERGENCY_LOGIN_ALLOWED_IPS=
```

Activation procedure:

1. Generate or rotate Grant Review credentials in the UHPH App Hub application editor.
2. Copy the one-time client ID and secret into Grant Review's ignored `.env`.
3. Set the real public HTTPS `HUB_URL`.
4. Configure a restricted administrator IP allowlist if emergency login is required.
5. Set `HUB_SSO_ENABLED=true` only after configuration and acceptance checks.
6. Run `php artisan config:clear` from an elevated shell if IIS file permissions block cache replacement.

Never commit or log `HUB_CLIENT_SECRET`. The normal `/login` route uses local authentication while SSO is disabled and redirects to the Hub when enabled. Hub sessions are forced through authorization again every 15 minutes by default so access revocations and role changes take effect. The emergency route is `/emergency-login` and only accepts active Grant Review administrators from configured IP addresses. Legacy user creation, invitation, set-password, and local role/status editing are disabled while Hub SSO is active. The Grant Review Users page links administrators to `/apps/admin/users/import` for centralized CSV onboarding; individual accounts can be created from the Hub Users page. Grant Review continues to manage local profile fields and round invitations after provisioning.

The SSO callback stores the Hub-issued application count, signed logout URL, and short-lived encrypted actor token in the local session. “All applications” appears only for users assigned to multiple enabled Hub apps and returns to the launcher without ending either session. “Sign out” destroys the Grant Review session and follows the trusted signed Hub logout URL. The Hub then sends the browser through each registered front-channel endpoint; `/auth/hub/logout` validates the opaque transaction with the Hub before clearing Grant Review, so signing out anywhere immediately clears all application and Hub sessions before showing the contextual login screen. Never construct or accept an arbitrary logout URL.

Grant Review application administrators may create identities and change Grant Review roles from `/apps/grant-review/admin/users`. Grant Review sends both its application credentials and the current actor token to the Hub's scoped management endpoint, then upserts the confirmed identity into its local profile database. Application administrators may assign `admin`, `submitter`, or `reviewer` only for Grant Review; they cannot grant global Hub administration, affect another application, disable/delete the central identity, or remove their own Grant Review administrator role. New identities receive the UHPH App Hub set-password invitation. After SSO, authenticated users with incomplete local profiles are restricted to `/complete-profile` until they provide phone, department, title, a 7–20 digit PeopleSoft ID, and investigator type (`pi` or `other`); early-stage and new-investigator flags are captured independently. Local profile fields and round invitations remain in Grant Review.

While Hub SSO is enabled, UHPH App Hub is authoritative for account deletion and application-access revocation. Opening Grant Review's Users page reconciles the complete authoritative Grant Review assignment list from the Hub: missing profiles are provisioned, role changes are applied, and local profiles absent from the Hub list are marked disabled and moved to Archived Users. Application admins may use “Revoke Access” to remove only the Grant Review assignment through the scoped Hub API. Never physically delete an SSO profile during revocation because submissions, reviews, decisions, and round history reference it; local deletion remains available only when Hub SSO is disabled.

Reviewer conflict-of-interest declarations are available to administrators at `/apps/grant-review/admin/conflicts` and inline on each submission's Review Results page. Browser checkbox values must be normalized as booleans before saving entries. COI submissions notify all active Grant Review administrators through the configured mailer; the default UH deployment uses `post-office.uh.edu:25` from `donotreply@uh.edu`.

The reusable Laravel protocol client is the local Composer package `uh/app-hub-client` at `E:\apps\packages\laravel-app-hub-client`. Keep authorization-code exchange, Hub response validation, session lifecycle, and Hub middleware in that package. Keep Grant Review user mapping and role-specific destinations behind `MapsHubIdentity` and `DeterminesLoginDestination` implementations in this application.

## Verification

```bash
composer exec --working-dir="E:/apps/grant-review" -- pint --test
composer exec --working-dir="E:/apps/grant-review" -- phpunit tests/Feature/Auth
composer exec --working-dir="E:/apps/grant-review" -- phpunit
```
