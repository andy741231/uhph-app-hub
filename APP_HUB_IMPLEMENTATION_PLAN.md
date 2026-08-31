# Transitional UHPH App Hub Implementation Plan

## Status

Implementation is complete through Phase 9 (Verification). Phase 10 transitional SSO is active for Grant Review and Flipbook administration; emergency-access policy, remaining user migration, and institutional OIDC are still pending.

## Confirmed Decisions

- Build a dedicated Laravel UHPH App Hub.
- Serve the main login and authorized application dashboard from `/apps`.
- Hub accounts are created by administrators only.
- Protect Flipbook administration while keeping public viewers and embeds accessible.
- Integrate Grant Review with the Hub while retaining its internal authorization roles.
- Keep phpMyAdmin's native database login.
- Do not integrate `swagtrack`, `cec-events`, or `community-partners` with the Hub.
- Design the integration for additional Laravel applications in the future.
- Replace the Hub's local login with institutional OIDC later without rebuilding application authorization.

## Applications in Scope

| Application | Hub authentication | Notes |
|---|---:|---|
| `grant-review` | Yes | Hub grants access; Grant Review retains application roles and local sessions. |
| `flipbook` | Administration only | Dashboard, upload, editing, deletion, and mutation APIs are protected. Public viewers remain accessible. |
| `phpmyadmin` | No | Keep native database login and protect separately through network or IIS controls. |
| Future Laravel apps | Yes | Use the reusable Hub integration. |

## Applications Out of Scope

No authentication changes will be made to:

- `swagtrack`
- `cec-events`
- `community-partners`

Their current API exposure is a separate concern and is not included in this implementation.

## Intended User Experience

### Main portal

```text
/apps
  -> login if unauthenticated
  -> authorized application dashboard if authenticated
```

The dashboard displays only enabled applications assigned to the current user. Hiding an application is not the security boundary; every launch is independently authorized by the Hub.

### Grant Review

```text
/apps/grant-review
  -> no local Grant Review session
  -> redirect to Hub authorization endpoint
  -> Hub verifies the grant-review assignment
  -> Hub issues a short-lived, one-time authorization code
  -> Grant Review callback exchanges the code server-to-server
  -> Grant Review maps or provisions the local user
  -> Grant Review creates its own Laravel session
  -> user reaches the role-appropriate dashboard
```

### Flipbook administration

```text
/apps/flipbook
  -> no local Flipbook administration session
  -> redirect to Hub authorization endpoint
  -> Hub verifies the flipbook assignment
  -> callback exchanges a one-time code
  -> Flipbook creates a local administration session
  -> user reaches the Flipbook dashboard
```

Public viewer and embed requests do not enter this flow.

## Phase 1: Deployment and Routing Foundation

### 1. Verify deployment

Before changing application routing, verify:

- Current IIS document-root and application mappings.
- PHP version and required extensions.
- Existing root `web.config` behavior.
- Whether Grant Review is configured as an IIS child application.
- How Hub assets will be served from `/apps`.
- How existing physical subdirectories interact with root rewrite rules.

If the current IIS layout cannot safely host Laravel at `/apps` without affecting child applications, stop and document the required IIS mapping rather than guessing or applying an unsafe workaround.

### 2. Create the Hub

Proposed source location:

```text
E:\apps\app-hub
```

Intended public routes:

```text
/apps
/apps/login
/apps/logout
/apps/admin/users
/apps/admin/applications
/apps/sso/authorize
/apps/sso/token
```

The Hub source must not be directly exposed as `/apps/app-hub`. Root routing must preserve the existing application paths.

### 3. Preserve excluded paths
(like to have a UI for admin to include or exclude these path in the future)
Root routing must not intercept:

```text
/apps/swagtrack/*
/apps/cec-events/*
/apps/community-partners/*
/apps/phpmyadmin/*
```

## Phase 2: Hub Authentication

### 4. Add local authentication

Use Laravel's standard session authentication with:

- Email and password.
- No public registration.
- Secure password hashing.
- Login rate limiting.
- Session regeneration after successful login.
- CSRF protection.
- Secure, HTTP-only session cookies in production.
- Password reset disabled initially unless reliable mail delivery is already configured.

Create the first administrator through a CLI command or seeder using an interactively supplied password. Never commit a password or secret.

### 5. Add Hub identities

Proposed user fields:

```text
users
- id
- public_id UUID
- name
- email
- password
- external_subject nullable
- status
- last_login_at nullable
- timestamps
```

Identity rules:

- `public_id` is the stable Hub identity sent to applications.
- `external_subject` stores the future OIDC `sub` identifier.
- Email is profile data and a controlled first-login linking aid, not the permanent cross-application identifier.
- Migrating the Hub to OIDC must not change the `public_id` applications already recognize.

## Phase 3: Central Application Authorization

### 6. Add the application registry

Proposed fields:

```text
applications
- id
- key
- name
- path
- callback_url
- client_id
- client_secret_hash
- enabled
- sort_order
- timestamps
```

Initial protected applications:

```text
grant-review
flipbook
```

### 7. Add application assignments

Proposed fields:

```text
application_user
- application_id
- user_id
- role nullable
- granted_by
- granted_at
- timestamps
```

Hub administrators can:

- Create and disable users.
- Grant and revoke application access.
- Assign an application-specific role when required.
- Review who granted access and when.

Initial roles:

```text
grant-review: admin, submitter, reviewer
flipbook: admin
```

### 8. Build the Hub dashboard

The dashboard must:

- List only assigned and enabled applications.
- Provide clear access-denied behavior.
- Prevent arbitrary redirect destinations.
- Record successful and denied launch attempts.
- Provide logout and account information.

## Phase 4: Transitional SSO Protocol

### 9. Implement a one-time authorization-code flow

Hub endpoints:

```text
GET  /apps/sso/authorize
POST /apps/sso/token
```

Application callbacks:

```text
/apps/grant-review/auth/hub/callback
/apps/flipbook/auth/callback.php
```

Authorization codes must be:

- Generated from a cryptographically secure random source.
- Stored only as hashes.
- Bound to one user and one application.
- Bound to an exact registered callback URL.
- Single-use.
- Short-lived, approximately 60 seconds.
- Consumed atomically to prevent replay.

Applications exchange codes server-to-server using confidential client credentials. User data, passwords, session identifiers, and long-lived tokens must not appear in redirect URLs.

Example token exchange response:

```json
{
  "subject": "stable-hub-uuid",
  "email": "user@example.edu",
  "name": "User Name",
  "application": "grant-review",
  "role": "reviewer"
}
```

This flow is modeled after OAuth's authorization-code flow but is not a general-purpose OAuth provider.

### 10. Security controls

Include:

- Exact callback URL allowlisting.
- No arbitrary `return_url` values.
- Hashed application secrets and constant-time comparisons.
- Code expiration and replay prevention.
- Server-side client authentication.
- HTTPS enforcement in production.
- Audit logging that excludes codes, cookies, passwords, and secrets.
- Secret rotation support.

Do not implement shared cross-application PHP sessions or place a long-lived JWT in a broad `/apps` cookie.

## Phase 5: Grant Review Integration

### 11. Add Hub authentication routes

Grant Review will use:

```text
GET /login
    -> redirect to Hub authorization

GET /auth/hub/callback
    -> exchange authorization code
    -> map or provision the user
    -> create a Laravel session

POST /logout
    -> destroy the Grant Review session
    -> redirect to the Hub
```

Grant Review keeps:

- Laravel `auth` middleware.
- Its local session.
- Its `admin`, `submitter`, and `reviewer` roles.
- Existing policies and ownership checks.
- Local user records and application-specific profile data.

### 12. Map Hub users

Mapping rules:

1. Match an existing Grant Review user by `sso_sub` and the Hub `subject`.
2. On first login only, optionally link one unambiguous active user with the same normalized email.
3. Save the stable Hub subject in `sso_sub`.
4. Reject ambiguous, disabled, or unauthorized accounts.
5. Synchronize the assigned Hub role with a supported Grant Review role.
6. Create the local session with Laravel authentication and regenerate it.

Recommended provisioning behavior:

- The Hub administrator assigns Grant Review access and a role.
- Grant Review creates a missing local user on first successful login.
- The Hub is the source of application access and role assignment.
- Grant Review remains the source of app-specific profile and activity data.

### 13. Preserve emergency access during rollout

During the pilot:

- Keep the current password login at a separate emergency route.
- Restrict it to designated Grant Review administrators.
- Prefer an IIS, VPN, or IP restriction if available.
- Do not show it in normal navigation.
- Keep current password reset and invitation flows until the Hub pilot succeeds.
- Disable the normal password login only after acceptance testing and explicit approval.

## Phase 6: Flipbook Integration

### 14. Classify every route and endpoint

Protect administration, including:

```text
index.php
upload.php
editor.php
upload endpoints
create/update/delete endpoints
metadata mutation endpoints
video mutation endpoints
```

Keep public where required:

```text
viewer.php
embed viewer
viewer assets
read-only viewer APIs
PDF delivery intended for public documents
```

Trace every endpoint before editing because some API files may combine read and write methods.

### 15. Add a reusable plain-PHP Hub client

The client will provide:

- Redirect to the Hub.
- Callback processing.
- Server-to-server code exchange.
- Local session creation and regeneration.
- Required-role checks.
- Logout.
- CSRF protection for administrative mutations.

Every protected page and mutation endpoint must enforce authentication independently. Protecting only the dashboard is insufficient.

### 16. Preserve public viewers

- Public viewer and embed URLs must not redirect to login.
- Administration controls must not be rendered for public users.
- Mutation endpoints must reject unauthenticated requests even when called directly.
- Public PDF behavior must be confirmed before changing download authorization.

## Phase 7: phpMyAdmin and Excluded Applications

### 17. phpMyAdmin

Make no phpMyAdmin authentication code changes.

Operational recommendations:

- Keep its native database login.
- Restrict it through VPN, IP allowlisting, internal networking, or IIS controls.
- Do not pass Hub or database credentials between systems.
- Do not treat hiding its link as access control.

### 18. Excluded applications

Do not change authentication in:

```text
swagtrack
cec-events
community-partners
```

## Phase 8: Future Laravel Applications

### 19. Extract reusable integration

After Grant Review is working, extract its Hub client into either:

- A small internal Composer package, or
- A standardized module for future Laravel applications.

It should include:

- Hub configuration.
- Login redirect.
- Callback controller.
- Authorization-code exchange client.
- User mapping contract.
- Authentication middleware.
- Logout behavior.
- Integration tests.

Each future application registers:

```text
application key
callback URL
client ID
client secret
supported roles
```

Future applications should not add independent password login for normal users.

## Phase 9: Verification

### 20. Hub tests

Test:

- Admin-only user management.
- Application assignment and revocation.
- Unauthorized application denial.
- Callback allowlist enforcement.
- Expired code rejection.
- Reused code rejection.
- Wrong client rejection.
- Disabled user and application rejection.
- Dashboard visibility.
- Open redirect prevention.

### 21. Grant Review tests

Test:

- `/login` redirects to the Hub.
- A valid callback creates a local session.
- Existing users link safely.
- Missing users provision according to the approved policy.
- Unsupported roles are rejected.
- Disabled users cannot log in.
- Existing role middleware and policies still work.
- Emergency login remains restricted.

### 22. Flipbook tests

Test:

- Public viewers and embeds remain accessible.
- Administration pages redirect to the Hub.
- Mutation APIs reject anonymous requests.
- Valid callbacks create local administration sessions.
- Logout destroys the local session.
- CSRF protection covers administrative mutations.

### 23. Manual acceptance accounts

Verify with:

1. A Hub administrator assigned to both apps.
2. A user assigned only to Grant Review.
3. A user assigned only to Flipbook.
4. A disabled user.
5. A user with no application assignments.
6. Direct protected URLs without using the Hub dashboard.
7. Expired and replayed authorization codes.
8. A public Flipbook embed.
9. Existing Grant Review role workflows.
10. phpMyAdmin's native login.

## Phase 10: Rollout

Status: Phases 1 through 9 are complete and verified. Transitional SSO is active for Grant Review and Flipbook administration. Remaining rollout work covers emergency-access policy, additional user migration, and the later institutional OIDC integration.

- [x] 1. Verify IIS and deployment assumptions.
- [x] 2. Build and test the Hub locally.
- [x] 3. Create the first Hub administrator (`mchan3@cougarnet.uh.edu`).
- [x] 4. Register Grant Review, provision its client credentials, and enable Hub SSO.
- [ ] 5. Enable and restrict Grant Review emergency login (decide the administrator IP allowlist).
- [x] 6. Pilot Hub login with the Hub administrator and verify the Grant Review admin session.
- [x] 7. Register and protect Flipbook administration using its ignored runtime environment file.
- [x] 8. Verify public Flipbook viewers remain accessible while administration and mutations require authentication.
- [ ] 9. Move all protected-application users to the Hub. Batch import with downloadable `name,email,application,role` CSV template and set-password invitations is available.
- [x] 10. Disable normal Grant Review password login after explicit approval.
- [ ] 11. Retain restricted emergency access until a later review.
- [ ] 12. Replace Hub local authentication with institutional OIDC when resources permit.

## Approval Boundary

Implementation begins only after explicit approval. The first execution step is deployment and IIS verification. Existing authentication routes will not be altered until the Hub has been built, tested, and registered as an authentication source.
