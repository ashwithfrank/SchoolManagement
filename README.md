# School Management System

A PHP 8 + MySQL/MariaDB web application for a school office, run on a local computer with **XAMPP** and
optionally used from other computers on the school LAN. Built with PDO, Bootstrap 5 and Font Awesome (both bundled -
no internet needed after install). Developed by Ashwith Frank. Powered by AlphaOmega Studio.

> **Read `docs/VERIFICATION.md` first.** It lists exactly what was executed and what was **not**.
> The build environment had no MySQL server and no browser session, so the database layer has never been run
> against a real database. Expect to report any first-run SQL problem; see "If something fails" below.

Roles: **Admin**, **Office Staff**, **Teaching Staff** (enforced on the server for every page and action).
Modules: dashboard, students (LKG-10, photos), academic years / classes / sections / promotion, employees,
buses & transport, fee structure / discounts / payments / receipts / reversals, exams & marks, reports (CSV + print/PDF),
users, backup & restore (encrypted, versioned), settings, audit log.

## 1. Install on XAMPP (Windows)

1. Install XAMPP (PHP 8.0 or newer). Start **Apache** and **MySQL** in the XAMPP control panel.
2. Check `C:\xampp\php\php.ini`: `extension=zip`, `extension=gd`, `extension=fileinfo`, `extension=mbstring`,
   `extension=pdo_mysql`, `extension=openssl` must be enabled (no leading `;`). Restart Apache after changes.
3. Unzip this project so the folder is **`C:\xampp\htdocs\SchoolManagement`** (the folder that contains `app`, `public`, `database`).
4. Create an empty database and a dedicated database user (do not use `root` for the application). Open
   `http://localhost/phpmyadmin` > SQL and run (change the password!):

   ```sql
   CREATE DATABASE school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'aosms'@'localhost' IDENTIFIED BY 'choose-a-long-random-password';
   GRANT ALL PRIVILEGES ON school.* TO 'aosms'@'localhost';
   FLUSH PRIVILEGES;
   ```
5. Open **http://localhost/SchoolManagement/public/** (the project root also redirects there).
   You are sent to the setup page (reachable **only from the server computer itself**):
   - Step 1: database host `127.0.0.1`, port `3306`, database `school`, user `aosms`, your password.
     This creates the tables and writes `app/config/config.php` (the only place the password is stored; it is outside `public/`).
   - Step 2: school name, first academic year, and the first **Admin** account. There is **no default login**.
6. Sign in. Suggested order: Academics (sections, subjects, coordinators) > Settings > Fees > Structure > Employees > Users > Students.

Only the `public/` folder is meant to be web-visible. The project also ships `.htaccess` files that deny direct web access to
`app/`, `storage/`, `database/`, `templates/`, `modules/` and `tests/` (Apache needs `AllowOverride All`, which XAMPP's default has).

## 2. LAN use

See `docs/LAN_DEPLOYMENT.md`. Short version: use a fixed IP for the server computer, allow only port 80 (or 443) in the
firewall, **never expose port 3306**, and open `http://<server-ip>/SchoolManagement/public/` from other computers.

## 3. Daily use

See `docs/USER_GUIDE.md` (roles, workflows). Backups: Backup & restore > create (encrypted recommended). Test a restore on a spare
computer before you rely on it.

## 4. Tests you can run

```
php tests/run.php          # pure-logic + static consistency checks (no database needed)
php -l <file>              # syntax check; see docs/VERIFICATION.md for the loop used
```
The database checklist for a first manual test is in `tests/README.md`.

## 5. If something fails

- Blank/"Something went wrong" page: open `storage/logs/app-errors.log` (newest at the bottom; it has the error reference shown on screen).
- Temporarily show details: set `'debug' => true` in `app/config/config.php`, reload, then set it back to `false`.
- PHP errors: `storage/logs/php-error.log`. Apache: `C:\xampp\apache\logs\error.log`. MySQL: `C:\xampp\mysql\data\mysql_error.log`.
- "Database connection failed": check MySQL is running, the user/password in `app/config/config.php`, and that the DB name exists.
- Setup page says an extension is missing: enable it in `php.ini` (see step 2) and restart Apache.
- Photo upload fails: check `upload_max_filesize` / `post_max_size` in `php.ini` and that `storage/student_photos` is writable.
- Reset when starting over: drop and recreate the database, delete `app/config/config.php`, reload the setup page.
- Send me the failing SQL message from `app-errors.log` (no passwords are written there) and I will fix it.

## 6. Project layout

```
public/        the only web-visible folder (index.php front controller, login, setup, assets)
app/Core       framework pieces: Db, Auth, Router, Csrf, Money, Marks, Migrator, BackupCrypto ...
app/Services   business rules + permission checks (one class per area)
modules/       controllers: modules/<module>/<action>.php
templates/     views
database/      schema.sql (baseline), seed.sql, migrations/
storage/       photos, backups, logs, tmp (not web accessible)
tests/         run.php + README.md
docs/          guides and the verification record
```
Third-party assets: see `THIRD_PARTY.md`.
