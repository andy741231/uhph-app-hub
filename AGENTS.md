# UHPH App Hub — Project Instructions

## Local Development

### Prerequisites
- **VPN access** to `uhph-server1.cougarnet.uh.edu` (production MySQL)
- **PHP 8.5+** with required extensions
- **Composer** installed

### Start the app

```bash
cd /Users/mchan3/CascadeProjects/uhph-app-hub
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 server.php
```

`server.php` is the local development router that emulates the production IIS `/apps` mount:

- `/apps/grant-review/*` → grant-review Laravel app (prefix stripped so Laravel routes match)
- `/apps/*` (everything else) → app-hub Laravel front controller
- Physical app directories (e.g. `flipbook/`) → served as-is
- Static files (favicon, css, js, images) → served directly

### URLs
- **Grant Review** (triggers SSO): http://localhost:8000/apps/grant-review
- **App Hub login**: http://localhost:8000/apps/login
- **Grant Review health check**: http://localhost:8000/apps/grant-review/up

### SSO Flow
1. Visit `/apps/grant-review` → redirects to `/apps/sso/authorize`
2. Hub sees no session → redirects to `/apps/login?application=grant-review`
3. Log in with Hub credentials
4. Hub issues authorization code → redirects back to `/apps/grant-review/auth/hub/callback`
5. Grant Review exchanges code for identity → logs you in

### First-time setup (if needed)

```bash
composer install --working-dir=app-hub
composer install --working-dir=grant-review

php artisan migrate --force --working-dir=app-hub
php artisan migrate --force --working-dir=grant-review

# Clear config caches after .env changes
php artisan config:clear --working-dir=app-hub
php artisan config:clear --working-dir=grant-review
```

### Verification

```bash
# Grant Review
composer exec --working-dir=grant-review -- pint --test
composer exec --working-dir=grant-review -- phpunit

# App Hub
composer test --working-dir=app-hub
```
