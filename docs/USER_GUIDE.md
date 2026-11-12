# User guide

## Roles (defaults; the server checks these on every page and every action)

| Area | Admin | Office Staff | Teaching Staff |
|---|---|---|---|
| Dashboard | yes | yes, incl. finance cards | yes, no finance |
| Students | view, add, edit, photo, enrol, activate | view, add, edit, photo, enrol | view; edit contact/address only; only their own sections |
| Academic years, classes, sections, subjects, coordinators | manage | view | view |
| Promotion | yes | no | no |
| Employees | manage | no | no |
| Buses & transport | manage | view + assign students / bus fee | no |
| Fee structure | yes | no | no |
| Discounts / assessment changes | yes | yes | no |
| Record payment | yes | yes | no |
| Reverse payment | yes | **no** | no |
| Exams, papers, marks | yes | no | exams view, set papers + enter marks for **assigned** class/subject |
| Reports | all | student + finance | student + academic |
| Users, backups, settings, audit log | yes | no | no |

An employee record is **not** a login. Create the account under Users and (for teachers) link it to the employee.
A teacher sees only sections where they are a coordinator or have a teaching assignment (Employees > assignments).
Admin can let all teachers enter marks for any class (Settings).

## Workflows
- **Admit a student:** Students > Add. Name and admission number are required; choose class/section for the current year.
  Identity (the student) is separate from the year-specific enrollment, so history is kept when classes change.
- **Photo:** open the student > upload JPEG/PNG/WebP (checked and re-encoded; stored outside the web folder).
- **Collect a fee:** Payments > Record payment > pick student, category (academic/transport), amount. Partial payments are allowed; overpaying the balance is refused;
  pressing the button twice cannot create two receipts. Then print the receipt (Print / Save as PDF).
- **Wrong payment:** open the receipt > Reverse (Admin) > reason. The receipt stays listed as reversed and is excluded from totals. Payments are never deleted.
- **Discount:** Fees > student > add discount with a reason. Discounts are voided, not deleted.
- **Bus:** Buses > add bus (insurance / FC dates warn on the dashboard). Transport > assign student with their own fee. Ending an assignment keeps history.
- **Exam marks:** Exams > new exam > Papers (class + subject + maximum + passing marks) > Enter marks > choose exam/class/section/subject > Load sheet.
  Blank = not entered; tick Absent for absence. Pass means scored >= passing marks; percentage = scored / maximum x 100.
- **New academic year / promotion:** Academics > years > create > Promotion: preview, fix flagged rows, confirm. Class 10 is never promoted automatically
  (choose Complete or Repeat). Old enrollments, marks and payments stay as they were.
- **Reports:** Reports > pick report > filter > sort by clicking headings > CSV or Print / PDF. The "PDF" is your browser's *Save as PDF* from the print view.
- **Backup / restore:** Backup & restore > create (set an encryption password; it cannot be recovered). To restore: Verify first, then Restore and type RESTORE.
  The system makes a safety backup of the current data first and signs everyone out afterwards.
