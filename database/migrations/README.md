# Migrations

`../schema.sql` is the baseline (version 1). Later changes go here as numbered files:

    002_add_something.sql
    003_another_change.sql

Rules:
- Never edit a migration that has already been released; add a new one.
- One statement per line-ending semicolon. Comment lines start with `--`.
- The installer and `public/upgrade.php` apply, in order, every file whose number is higher than the
  highest version recorded in the `schema_migrations` table.
- Take a backup (Backup & Restore page) before upgrading a live system.
