# Flipbook Project Instructions

## Deployment

- Flipbook runs under IIS at `/apps/flipbook`.
- Keep `BASE_PATH_OVERRIDE=/apps/flipbook` in production if automatic detection is unavailable.
- Public viewer routes must remain accessible without Hub authentication.
- Dashboard, upload, editor, and all API mutations require Flipbook administrator access when Hub SSO is enabled.
- Regenerate the Flipbook favicon with `php E:/apps/flipbook/scripts/generate-favicon.php` (requires GD); it renders the navbar Font Awesome `book-open` glyph white on the red rounded square and writes `favicon.png` + `favicon.ico` in the flipbook root. The `scripts/` directory is blocked from direct HTTP access by `web.config` and `.htaccess`.

## App Hub SSO

SSO is active. Runtime configuration is loaded from the ignored `E:\apps\flipbook\.env`, which must remain blocked by IIS and excluded from Git:

```dotenv
FLIPBOOK_HUB_SSO_ENABLED=true
FLIPBOOK_HUB_URL=https://uhph.uh.edu/apps
FLIPBOOK_HUB_CLIENT_ID=
FLIPBOOK_HUB_CLIENT_SECRET=
FLIPBOOK_HUB_CALLBACK_URI=/apps/flipbook/auth/callback.php
FLIPBOOK_HUB_VERIFY_TLS=true
FLIPBOOK_HUB_SESSION_REVALIDATION_MINUTES=15
BASE_PATH_OVERRIDE=/apps/flipbook
```

Never commit or log `FLIPBOOK_HUB_CLIENT_SECRET`. The client must have the Flipbook `admin` role. When SSO is enabled, administrator sessions reauthorize through the Hub every 15 minutes by default.

## Access boundaries

Public:

- `viewer.php`
- `api/flipbooks.php?slug=...`
- GET requests to `api/videos.php`, `api/links.php`, and `api/text.php`
- `api/pdf.php`
- `api/download.php`

Administrative:

- `index.php`
- `upload.php`
- `editor.php`
- `api/upload.php`
- Flipbook list and ID reads
- All POST, PUT, and DELETE API operations

All administrative mutations require both an authenticated administrator and the `X-CSRF-Token` request header.

## Verification

```bash
php "E:/apps/flipbook/tests/auth_test.php"
php -l "E:/apps/flipbook/includes/auth.php"
php -l "E:/apps/flipbook/auth/login.php"
php -l "E:/apps/flipbook/auth/callback.php"
php -l "E:/apps/flipbook/auth/logout.php"
```
