-- Ownership + visibility for flipbooks.
-- Additive only. Safe to re-run: every statement is guarded by the PHP runner
-- (scripts/migrate-ownership.php) which checks INFORMATION_SCHEMA first.
--
-- visibility:
--   public   - listed in the public gallery, viewable and embeddable by anyone
--   unlisted - not listed, but anyone with the link can view/embed
--   private  - only the owner and Flipbook admins can view; embeds disabled

ALTER TABLE flipbooks
    ADD COLUMN owner_subject CHAR(36) NULL AFTER settings_json,
    ADD COLUMN owner_name VARCHAR(255) NULL AFTER owner_subject,
    ADD COLUMN owner_email VARCHAR(255) NULL AFTER owner_name,
    ADD COLUMN visibility ENUM('public', 'unlisted', 'private') NOT NULL DEFAULT 'private' AFTER owner_email,
    ADD INDEX idx_owner_subject (owner_subject),
    ADD INDEX idx_visibility (visibility);

-- Backfill: pre-existing flipbooks were created by the sole admin before
-- ownership existed and were already reachable by public link, so they keep
-- that behaviour. Only touches rows that have no owner yet.
UPDATE flipbooks
SET owner_subject = '3700e35d-62b3-4a0a-96df-b9d965dc4896',
    owner_name = 'M Chan',
    owner_email = 'mchan3@cougarnet.uh.edu',
    visibility = 'public'
WHERE owner_subject IS NULL;
