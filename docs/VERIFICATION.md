# What was and was not verified

Honest record for this delivery (version 1.0.0). Nothing below is claimed unless it was actually executed.

## Executed (PHP 8.3.6 CLI in the build sandbox)
- `php -l` on all 147 PHP files: **no syntax errors**.
- `php tests/run.php`: **294 passed, 0 failed**. Covers pure logic (money, marks, fee formulas, backup encryption round-trip/wrong password/tamper,
  SQL statement splitter, CSRF token check, CSV formula guard, date validation, permission defaults per role) and static consistency checks
  (schema foreign-key targets, every table named in PHP SQL exists in `schema.sql`, every controller registered and every registered action has a file,
  every `url()` target and `View::` template exists, every POST form has a CSRF field, no inline script/handlers, no eval/exec, no md5/sha1).
- Smoke test with PHP's built-in web server (no database present): unconfigured app redirects to the setup page; setup page renders (200); CSS and font
  assets are served; security headers (CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy) are sent; `photo.php` without a session returns 403.

## NOT executed (build sandbox had no MySQL/MariaDB and no interactive browser)
- **Any SQL against a real database.** `schema.sql`, `seed.sql`, every query in `app/Services`, the migration runner, the SQL dump/loader used by backup/restore.
  The SQL was written conservatively for MySQL 5.7+/MariaDB 10.4+ and cross-checked only against table names, but column-level typos or dialect problems can still exist.
- Login, sessions, role enforcement against real users, throttling, idle timeout.
- Student create/edit, photo upload, enrollment, promotion, fee assessment, payments (idempotency, reversal), discounts, transport, exams/marks, reports queries, CSV/print output.
- Backup creation, encrypted backup, verify, restore (DB + photos swap and automatic rollback), import/rescan, synced-folder copy.
- Rendering in a real browser (layout, JavaScript behaviours such as dependent dropdowns, double-submit guard, confirm dialogs).
- Browser compatibility, performance with large data, concurrency under several simultaneous users.

The first-run manual checklist in `tests/README.md` is the way to close these gaps. Treat the first real run as the first integration test.

## Known limitations
- Role permissions are the fixed defaults in code; there is no screen to edit them.
- "PDF" = the browser's *Save as PDF* from the print view; there is no server-side PDF library.
- Cloud backup = the encrypted file is copied into a folder you choose (OneDrive/Google Drive/network share); **no cloud API is integrated** and none is claimed.
- No attendance module, no SMS/e-mail, no two-factor authentication, no e-mail based password reset.
- Single school, single server; LAN use is documented, not load-tested. Plain HTTP unless you configure HTTPS.
- Restore replaces the database wholesale (MySQL cannot roll back structure changes); the automatic safety backup is the recovery path if a restore fails midway.
- Backups are built in PHP (no `mysqldump`), so very large databases need a generous `memory_limit`/`max_execution_time` (the code raises them for the request).
- No demo/sample data is shipped.
