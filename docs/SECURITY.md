# Security notes

Implemented in code (reviewed by reading and by `tests/run.php`; see VERIFICATION.md for what was run):
- PDO with native prepared statements everywhere; request values never concatenated into SQL.
- Passwords hashed with `password_hash` (bcrypt); login throttling (per user and per IP); session id regenerated at login; idle (30 min) and absolute (12 h) timeouts; session bound to the browser's user-agent hash.
- CSRF token required on every POST (enforced centrally in the router); output escaped with `e()`.
- Role permissions checked in the router **and** again inside each service method that changes data.
- Uploads: size limit, MIME sniffing + image decode, re-encode with GD, random file names, stored outside `public/`, served only to signed-in users through `photo.php`.
- Headers: CSP (no inline script, no external hosts), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy.
- Secrets: DB password only in `app/config/config.php` (not in `public/`, not in the ZIP, `.gitignore`d, `.htaccess` deny-all).
- Audit log for sign-ins, data changes, exports, backups and restores (append-only from the application).
- Backups: optional AES-256-GCM encryption with PBKDF2-derived key; restore validates checksum and manifest first.
- CSV export neutralises spreadsheet formulas.

Limits you should know:
- Plain HTTP on a LAN exposes passwords to anyone who can sniff the network. Use HTTPS (docs/LAN_DEPLOYMENT.md).
- Role permissions are fixed at the defaults in code (there is no screen to edit them).
- Anyone with file access to the server (or its disk) can read the database and the config file; protect the computer physically and with Windows accounts.
- No two-factor authentication, no password expiry, no e-mail based reset (an Admin resets passwords).
