# Grant Review

RCMI Pilot Grant Program review application (Laravel), deployed as an IIS child application under the UHPH App Hub at `/apps/grant-review`.

## Authentication runbook

Grant Review signs in through the UHPH App Hub when its ignored `.env` sets `HUB_SSO_ENABLED=true`; `false` keeps the legacy local login form. The flag is Grant Review's own switch — the Hub's `HUB_LOGIN_MODE` (`local`, `sso`, or `hybrid`, set only in `app-hub/.env`) independently controls which sign-in methods the Hub itself offers.

| Setting | Production (required) | Local |
| --- | --- | --- |
| `HUB_SSO_ENABLED` | `true` | `false` exercises legacy local login only — it does not test centralized sign-in or coordinated logout; `true` for end-to-end SSO testing |
| `HUB_URL` | `https://uhph.uh.edu/apps` | Environment-specific Hub, e.g. `http://localhost:8000/apps` |
| `HUB_CALLBACK_URI` | `/apps/grant-review/auth/hub/callback` | Same path, matching the local Hub registration exactly |
| `HUB_CLIENT_ID` / `HUB_CLIENT_SECRET` | From the Hub application editor (shown once) | Generated locally — never copy production secrets |
| `HUB_VERIFY_TLS` | `true` — never disable as a workaround | `true` |

Before enabling in any environment: register credentials in that environment's Hub, match the callback URI to the registered `/apps/...` path exactly, and confirm each account has a Grant Review assignment — the acceptance round trip can only run after the integration is on. Then set `HUB_SSO_ENABLED=true` and clear that app's cache — `composer exec --working-dir="E:/apps/grant-review" -- php artisan config:clear`; no `npm run build` is needed for environment changes. Verify in a browser: a fresh SSO sign-in plus a coordinated global-logout round trip.

Diagnose failures by status and endpoint: a **403** during Hub authorization can indicate a missing or revoked assignment, while a **403** from the scoped managed-users API can indicate a rejected actor token — check which endpoint failed; a **502** during the callback can indicate an exchange, connectivity, or Hub response-validation failure (`HUB_URL`, credentials, callback match, or TLS).

Hub and legacy local passwords are independent stores: mapping an SSO identity preserves the existing `password_hash` for every role during the transition (hashes already cleared by earlier releases are not restored by rolling back). Never sync passwords to fix a login problem. For a full local setup — standalone or end-to-end SSO — see `docs/local-authentication-setup.md`.

The Hub coordinates global logout through the registered frontchannel endpoint `/apps/grant-review/auth/hub/logout`, which returns **404 while `HUB_SSO_ENABLED=false`**. The production cutover completed on 2026-10-07: `HUB_SSO_ENABLED=true` is live, so the endpoint now participates in coordinated logout normally; the 404 remains relevant only for other environments that disable the flag while the endpoint stays registered. It validates the Hub's single-use, two-minute logout token before clearing the local session; an expired logout URL cannot be reused — sign out again for a fresh transaction.

With SSO enabled, passwords are owned centrally by the Hub: Grant Review's forgot/reset-password pages redirect to the Hub, local password submissions return 405, and the profile page links to the Hub's `/apps/account/password` page (or explains that CougarNet manages the password when the Hub is in `sso` mode).

`.env` is gitignored along with `bootstrap/cache` config caches and `public/build`: git carries code and docs only, and each deployment keeps its own environment — never edit config defaults to push production values onto local.

## COI & confidentiality audit trail

- **Where things live**: reviewers declare per-proposal conflicts at `/reviewer/conflicts/{round}`; admins review coverage at `/admin/conflicts` and audit every declaration version (current and superseded) at `/admin/conflicts/{declaration}` ("View declaration history" on each submitted row). Confidentiality acceptances are shown on the admin user profile.
- **What is stored**: each `conflict_of_interest_declarations` row snapshots the canonical COI policy (`coi_policy_version`, `coi_policy_content`, `coi_policy_acknowledged_at`, same instant as `declared_at`); `confidentiality_agreements` stores the agreed confidentiality text per reviewer + cycle + document version. Canonical wording lives in `App\Support\ConflictOfInterestPolicyDocument` and `App\Support\ConfidentialityAgreementDocument`.
- **Legacy limitation**: declarations recorded before the snapshot columns exist keep `NULL`s and render "COI policy acceptance details were not recorded" — never assume today's wording for old rows; rows predating per-proposal responses fall back to `conflict_of_interest_entries` with an incomplete-coverage warning.
- **Policy changes**: when the policy text or the linked NIH URL changes, bump `ConflictOfInterestPolicyDocument::VERSION` and update `sections()` manually — the external NIH page is not archived, only our local policy text and the link are stored. The declaration form posts a `coi_policy_digest` (SHA-256 of version + text); forms opened before a change are rejected on submit and the reviewer must reload and re-read the policy.
- **Deployment**: run `php artisan migrate --force` and `npm run build` when deploying — production migrations are never automated.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
