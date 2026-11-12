# Tests

## Automated (no database)
```
php tests/run.php
```
Covers: Money, Marks (percentage, pass/fail at the boundary, absent / not-entered), fee calculation (total = academic + bus - discounts, balance = total - valid payments),
backup encryption round trip / wrong password / tamper detection, SQL splitter, schema foreign-key targets, every table named in PHP SQL exists in the schema,
permission defaults per role, CSRF checks, CSV formula guard, date validation, router registry vs controller files, `url()` targets, templates exist,
every POST form has a CSRF field, no inline scripts/handlers (CSP), no eval/exec, no md5/sha1.
Exit code 0 = all passed.

## Manual checklist on a real MySQL (NOT yet executed by the author)
1. Fresh DB > setup page creates tables (29) and the first Admin; second visit to setup is refused.
2. Create Office and Teaching users; confirm Office cannot open `?m=users` / `?m=backups` (403) and Teaching cannot open `?m=payments&a=record` (403) even by typing the URL.
3. Add classes' sections, a student (with photo), enrol; duplicate admission number and duplicate enrollment in the same year are refused.
4. Set a fee structure; assign a bus fee; record two partial payments; try to overpay (refused); double-click Record (one receipt); reverse one (totals drop, receipt stays).
5. Exam with a paper max 100 / min 35: enter 35 (Pass), 34.99 (Fail), leave blank (Not entered), tick Absent; try 101 (refused).
6. Promotion preview > confirm; Class 10 row must ask Complete/Repeat; rerun does not duplicate.
7. Each report: sort, filter, page, CSV opens in Excel with Rupee amounts as numbers, Print view > Save as PDF.
8. Backup (encrypted) > Verify with right and wrong password > change some data > Restore > data and photos are back; safety backup exists.
9. Login: 5 wrong passwords lock the user for 15 minutes; idle 30 minutes signs out.
