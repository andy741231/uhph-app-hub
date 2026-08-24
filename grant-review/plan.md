A small internal grants portal for UH with three roles: Admin (program staff), Submitter (preselected, submits PDF), Reviewer (scores submissions). Two funding rounds per year. No public signup — everyone is provisioned by admin, either one-by-one or via CSV.

## Stack

Production is Windows Server / IIS / PHP. Given the scale (dozens to low-hundreds of users, internal tool):

- **Backend + Frontend**: Laravel 12.x on IIS via FastCGI — native PHP hosting, no extra runtime. Blade templating for server-rendered pages. Inertia.js + Vue is an option later if an SPA feel is wanted, but Blade alone is the right starting point.
- **Database**: MySQL 8.0+ — Eloquent ORM maps directly onto the schema below. Migrations are first-class.
- **File storage**: Local disk on the Windows Server, outside the web root, on a drive covered by the backup routine. Laravel's Storage facade serves PDFs through an authenticated route — no direct file URLs, no path traversal.
- **Auth**: Laravel Breeze (email + password, bcrypt, sessions). Password reset, set-password invite flow, and role middleware are built in. SSO (UH Entra ID / OIDC) added later via Laravel Socialite — see below.
- **Email**: Laravel Mail facade with SMTP driver pointed at UH's mail relay. Get host, port, auth method, TLS requirement, and whether the server IP needs allow-listing from UH IT before building the email piece — it's the dependency most likely to surprise you.

### SSO: build without it, design for it

SSO may not be immediately available from UH IT. Build with password auth first; swap SSO in later without throwing anything away. Laravel's multi-guard auth system is designed for exactly this.

- `sso_sub` VARCHAR(255) nullable column is on `users` now (zero cost, avoids a migration later).
- When SSO is ready: add a Laravel Socialite OIDC driver. The SSO guard looks up users by `sso_sub`, falls back to email for initial migration. Both guards run side by side during transition.
- The invite/set-password flow stays for non-SSO users; SSO skips it entirely (users are pre-provisioned, SSO handles authentication).
- Once all users are on SSO, set `password_hash` to NULL. The invite flow remains useful for provisioning new users before their SSO account is confirmed.

### Reviewer design (Phase 3-1a architecture)

**Blind review — resolved.** Default: **on**. Reviewers do not see submitter name/department/email; admins always see full identity. Rationale: blind review is the safer default for a university grants process (reduces bias, matches peer-review norms) and is trivial to disable if program staff want open review instead. Controlled by `config('reviews.blind_review')` (env: `REVIEWS_BLIND_REVIEW`, default `true`) — a single flag, not a code change.

**Known limitation**: this hides identity at the database-field level only. It cannot redact identifying content (names, affiliations, acknowledgments) that may appear inside the PDF itself — that's a document-content concern outside this system's scope. Flag this to program staff if true double-blind review is required; they may need to instruct submitters to omit identifying info from the PDF body.

**Enforcement point**: `App\Support\ReviewerSubmissionView` (`app/Support/ReviewerSubmissionView.php`) is the single presenter reviewer-facing views must render through — never pass a `Submission` model (with its `submitter` relation) directly to a reviewer view, since that bypasses the blind-review gate. Admin views are unaffected; admins query `Submission` directly.

**Draft-review state model**: mirrors the submission draft/submit pattern already implemented in Phase 2-2b.
- A `Review` row is created eagerly when a `ReviewAssignment` is created (empty `score`/`comments`, `submitted_at` null) — this makes "review completion status" a simple query (`submitted_at IS NULL` vs not) without existence checks.
- States: **unopened** (score/comments never saved) → **draft** (reviewer has saved at least once, `submitted_at` still null) → **submitted** (`submitted_at` set, locked from further edits — same lock pattern as `SubmissionPolicy::update`).
- Phase 3-1b must set `submission.status = 'under_review'` when the *first* `ReviewAssignment` for that submission is created (per the schema comment on `submissions.status`).

**PDF viewer integration**: reuse the existing authenticated route (`submissions.pdf`, Phase 2-1) — no new route or storage change needed. Verified `Storage::response()` defaults to `Content-Disposition: inline`, so `<iframe src="{{ route('submissions.pdf', $submission) }}" title="Submission PDF">` renders inline in-browser under the same per-request authorization already enforced by `SubmissionPolicy::view`. Provide a plain "Open in new tab" fallback link next to the iframe for mobile/accessibility cases where inline PDF rendering is unreliable.

### Open questions before build

- ~~Blind review~~ — resolved above.

### UI/UX

All phases that produce user-facing views must invoke the `/ui-ux-pro-max` skill before writing Blade templates. The skill provides design system guidance (color palettes, font pairings, UX guidelines, component patterns) — use it to keep the portal visually consistent and accessible (WCAG 2.1 AA, required for a UH public institution tool). Applies to: Phase 1-2 (admin CRUD views), Phase 2-2a (submission form), Phase 3-2a (reviewer dashboard), and any later view work.

## Database schema (MySQL)

```sql
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255),              -- NULL until set via invite link; NULL again after SSO migration
  name          VARCHAR(255) NOT NULL,
  department    VARCHAR(255),
  role          ENUM('admin','submitter','reviewer') NOT NULL,
  status        ENUM('invited','active','disabled') DEFAULT 'invited',
  invite_token_hash VARCHAR(255),          -- store HASH, not raw token; raw value only in email link
  invite_expires_at DATETIME,
  sso_sub       VARCHAR(255),              -- nullable; populated when UH SSO is wired in
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE rounds (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,       -- "Spring 2027"
  opens_at    DATETIME NOT NULL,
  deadline_at DATETIME NOT NULL,
  status      ENUM('draft','open','closed') DEFAULT 'draft',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE round_invitations (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  round_id    INT NOT NULL,
  user_id     INT NOT NULL,                -- must have role='submitter'
  invited_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (round_id) REFERENCES rounds(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uniq_round_user (round_id, user_id)
);

CREATE TABLE submissions (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  round_id         INT NOT NULL,
  submitter_id     INT NOT NULL,
  title            VARCHAR(500) NOT NULL,
  abstract         TEXT,
  amount_requested DECIMAL(12,2),
  pdf_path         VARCHAR(500) NOT NULL,  -- file path or storage key
  status           ENUM('draft','submitted','under_review','decided') DEFAULT 'draft',  -- under_review set on first review_assignment creation
  submitted_at     DATETIME,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (round_id) REFERENCES rounds(id),
  FOREIGN KEY (submitter_id) REFERENCES users(id),
  UNIQUE KEY uniq_round_submitter (round_id, submitter_id)
);

CREATE TABLE review_assignments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  submission_id INT NOT NULL,
  reviewer_id   INT NOT NULL,              -- must have role='reviewer'
  assigned_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id),
  FOREIGN KEY (reviewer_id) REFERENCES users(id),
  UNIQUE KEY uniq_submission_reviewer (submission_id, reviewer_id)
);

CREATE TABLE reviews (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  review_assignment_id  INT NOT NULL UNIQUE,
  score                 DECIMAL(5,2),      -- or JSON column for multi-criteria (MySQL 8+)
  comments              TEXT,
  submitted_at          DATETIME,
  FOREIGN KEY (review_assignment_id) REFERENCES review_assignments(id)
);

CREATE TABLE decisions (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  submission_id  INT NOT NULL UNIQUE,
  outcome        ENUM('funded','not_funded') NOT NULL,
  amount_awarded DECIMAL(12,2),
  decided_by     INT NOT NULL,             -- admin user id
  decided_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id),
  FOREIGN KEY (decided_by) REFERENCES users(id)
);
```

## Build phases

Phase 0 (GLM 5.2): Foundation & scaffolding
- Laravel migration files (convert the SQL schema above into `database/migrations/*.php`)
- Eloquent model classes + relationships (User, Round, RoundInvitation, Submission, ReviewAssignment, Review, Decision)
- Database seeders (first admin + test data)
- Laravel FormRequest validation classes (email, required, file type/size rules)

Phase 1-1 (DeepSeek V4 Pro): Users & rounds
- Admin login (seed the first admin manually in DB)
- Create/edit Round
- Add submitters: single form (name/email/dept) or CSV upload → bulk insert into users (role='submitter', status='invited') + round_invitations
- Invite email via UH SMTP with set-password link (hashed token in invite_token_hash, expires in e.g. 7 days)

Phase 1-2 (GLM 5.2): Blade CRUD view scaffolding for Rounds & Users admin (/ui-ux-pro-max)
- List/create/edit forms for Rounds and Users admin

Phase 2-1 (Claude Sonnet 5): PDF upload + authenticated file-serving route
- Authenticated upload endpoint with file type/size validation
- File-serving route with per-user authorization (no direct file URLs, no path traversal)

Phase 2-2a (GPT-5.6 Luna): Submission form UI (/ui-ux-pro-max)
- Blade form: title/abstract/amount + PDF upload fields (same pattern as Round/User create forms)
- Submitter logs in → sees Rounds they're invited to (join round_invitations on their user_id, filter status='open')

Phase 2-2b (DeepSeek V4 Pro): Draft/submit state, deadline enforcement
- Save as draft or submit (locks editing, sets submitted_at), wired to Phase 2-1 upload route
- Server-side deadline enforcement on submit (regardless of rounds.status)

Phase 3-1a (Claude Sonnet 5): Reviewer assignment & dashboard architecture — DONE
- Design decisions recorded above: blind-review hiding (`ReviewerSubmissionView` + `config/reviews.php`), draft-review state model, PDF viewer integration (reuses Phase 2-1 route, no changes needed)

Phase 3-1b (GPT-5.6 Luna): Reviewer-assignment checkbox UI — DONE
- Admin assigns reviewers to submissions (checkbox UI, writes to review_assignments) — same checkbox-to-pivot-table pattern as Phase 1-2's round-invitation checkboxes
- Sets submission.status = 'under_review' when assignments exist and creates the empty Review row eagerly per the draft-review state model
- Prevents unassigning a reviewer whose review has already been submitted

Phase 3-2a (DeepSeek V4 Pro): Reviewer dashboard implementation (/ui-ux-pro-max) — DONE
- Reviewer dashboard: their assignments, embedded PDF viewer, score + comments form (fiddly state per Phase 3-1a design)

Phase 3-2b (GPT-5.6 Luna): Admin aggregate score view — DONE
- Admin view: submissions with avg score across all reviews, review completion status (read-only aggregate table, same pattern as dashboard stat cards)

Phase 4-1 (GPT-5.6 Luna): Decisions — DONE
- Admin sets outcome + amount per submission → decisions table (single-record CRUD form, same shape as Round create/edit)
- Optional auto-email to submitter via UH SMTP (opt-in via `DECISION_NOTIFY_SUBMITTER=true`; default false)

Phase 4-2 (GLM 5.2): CSV export of round results [COMPLETE]
- Join submissions + decisions + avg scores, output CSV with headers

Phase 5-1 (GLM 5.2): Email templates — PENDING
- Laravel Mailable classes + Markdown Blade views for:
  - Submitter invite (set-password link, 7-day expiry) — wires into existing `SetPasswordController` invite flow
  - Password reset (Breeze default, restyled to UH brand)
  - Reviewer reminder (admin-triggered: "you have N unsubmitted reviews for round X, deadline Y")
  - Decision notification (already implemented in Phase 4-1; restyle to UH brand if needed)
- All templates use UH brand colors/typography per `ui-ux-pro-max` skill
- Plain-text fallback view for every Mailable (accessibility + UH SMTP relay compatibility)
- Configurable sender (`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`) — already in `.env`
- Reminder mail is admin-triggered via a new `POST /admin/rounds/{round}/remind-reviewers` route (no cron/scheduler dependency for v1)

Phase 5-2 (Claude Sonnet 5): Windows/IIS deployment — PENDING
- IIS FastCGI application pool config for PHP 8.3+
- `web.config` rewrite rules (Laravel's IIS starter config, adapted)
- Storage/bootstrap/cache directory permissions for IIS app pool identity
- PDF storage path outside web root (per `plan.md` line 9) — verify `config/filesystems.php` `local` disk root
- GitHub repo setup (`uh-proposal-reviewer`) + `.env.example` committed (real `.env` stays gitignored)
- Deployment runbook: `git pull` → `composer install --no-dev` → `php artisan migrate --force` → `php artisan config:cache` → `php artisan route:cache` → `php artisan view:cache`
- Optional: self-hosted GitHub Actions runner on Windows Server for CI/CD (v2 alternative per message 11)

Phase 5-3 (Claude Sonnet 5): Code review pass & integration debugging — PENDING
- End-to-end walkthrough of every role flow: admin (rounds, users, assignments, decisions, exports), submitter (invite → set-password → submit), reviewer (assignment → score → submit)
- Policy audit: every controller route has a matching `Policy` authorization check
- N+1 query audit on aggregate views (dashboard, review-results, CSV export)
- FormRequest validation audit (every mutating route has a FormRequest)
- CSRF + mass-assignment check on every admin form
- Blind-review enforcement audit: confirm no reviewer view bypasses `ReviewerSubmissionView`
- Fix any issues found; regression-test after each fix

## Model assignment (per ai-agent.md tiers)

Phase-specific and scaffolding model assignments are in the build phases above. This table covers cross-cutting work only.

| Work item | Tier | Model | Rationale |
|---|---|---|---|
| App architecture, auth (login, invite/set-password, password reset), session & role-permission middleware | 1 | Claude Sonnet 5 | Auth/security is Tier 1 only (assignment rule 2) — **DONE** across Phases 1-1 through 4-1 |
| Email templates (invite, reset, reminder, decision) | 3 | GPT-5.6 Luna / GLM 5.2 | One-shot generation from spec — **Phase 5-1** |
| Windows/IIS deployment: FastCGI config, app pool, deploy pipeline | 1 | Claude Sonnet 5 (alt: GPT-5.6 Sol) | Build tooling/ops is Tier 1; Sol has the strongest Terminal-Bench if it gets shell-heavy — **Phase 5-2** |
| Code review pass, integration debugging | 1 | Claude Sonnet 5 | Debugging is the most agentic task — **Phase 5-3** |
| Tier 1 has looped twice on the same problem | S | Claude Fable 5 | Nuclear option per escalation rule |

**Validation & escalation**: before Phase 1-1 give V4 Pro, GLM 5.2, and Luna one identical well-specified task (e.g. the CSV import endpoint for `round_invitations`) and compare output — costs pennies and calibrates the tiering for this stack. If GLM 5.2 loops, diverges, or fails review on any Tier 3 task, escalate that specific task to V4 Pro (Tier 2) — do not retry on the same tier (assignment rule 4).

## Deployment

- Create GitHub repo: `uh-proposal-reviewer`
- User will deploy manually on Windows server

### Database

- **Host**: `uhph-server1.cougarnet.uh.edu` (existing UH MySQL server)
- **Database name**: `proposal-reviewer`
- **Username**: `web_app`
- **Password**: obtain from UH IT / password manager — **never commit to the repo**. Store in Laravel's `.env` (which is gitignored) as `DB_PASSWORD`. For GitHub Actions deploys, use a repository secret.
- Laravel `.env` config:
  ```
  DB_CONNECTION=mysql
  DB_HOST=uhph-server1.cougarnet.uh.edu
  DB_DATABASE=proposal-reviewer
  DB_USERNAME=web_app
  DB_PASSWORD=<from secrets manager>
  ```
