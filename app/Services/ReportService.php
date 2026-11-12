<?php
namespace App\Services;

use App\Core\{Auth, Db, Marks, Money, UserError};

/**
 * Report catalogue. Each report returns ['columns' => [[label, type]], 'rows' => [[...]], 'summary' => [label => text]].
 * Column types: text | num | money (paise) | date. Money cells hold integer paise.
 */
class ReportService
{
    public static function definitions(): array
    {
        return [
            'students' => ['title' => 'Student list', 'desc' => 'Students with class, section and contact details.', 'perm' => 'reports.students', 'filters' => ['year', 'class', 'section', 'student_status', 'q']],
            'strength' => ['title' => 'Class and section strength', 'desc' => 'Number of boys, girls and total students in each section.', 'perm' => 'reports.students', 'filters' => ['year']],
            'admissions' => ['title' => 'Admission report', 'desc' => 'Students admitted between two dates.', 'perm' => 'reports.students', 'filters' => ['from', 'to']],
            'collection' => ['title' => 'Fee collection (date range)', 'desc' => 'Receipts between two dates, including reversed ones shown separately.', 'perm' => 'reports.finance', 'filters' => ['from', 'to', 'year', 'class', 'section', 'category', 'method']],
            'pending' => ['title' => 'Paid / pending fees', 'desc' => 'Assessed, paid and outstanding fees per student.', 'perm' => 'reports.finance', 'filters' => ['year', 'class', 'section', 'fee_status']],
            'class_collection' => ['title' => 'Class-wise collection', 'desc' => 'Assessed, discounts, collected and pending per class.', 'perm' => 'reports.finance', 'filters' => ['year']],
            'year_collection' => ['title' => 'Academic-year collection', 'desc' => 'Totals for every academic year.', 'perm' => 'reports.finance', 'filters' => []],
            'bus_fees' => ['title' => 'Bus-wise transport fees', 'desc' => 'Riders, transport fee assessed, collected and pending per bus.', 'perm' => 'reports.finance', 'filters' => ['year']],
            'bus_allocation' => ['title' => 'Bus allocation', 'desc' => 'Which student travels on which bus, with pickup point and fee.', 'perm' => 'reports.students', 'filters' => ['year', 'bus']],
            'results' => ['title' => 'Exam results (class)', 'desc' => 'Subject marks, percentage and pass/fail for a class.', 'perm' => 'reports.academic', 'filters' => ['exam', 'class', 'section']],
            'subject_results' => ['title' => 'Subject-wise results', 'desc' => 'Marks and pass/fail for one subject in an exam.', 'perm' => 'reports.academic', 'filters' => ['exam', 'class', 'section', 'subject']],
            'staff' => ['title' => 'Staff report', 'desc' => 'Employees with designation, subjects and login status.', 'perm' => 'reports.staff', 'filters' => ['staff_status']],
        ];
    }

    public static function run(string $key, array $f): array
    {
        $defs = self::definitions();
        if (!isset($defs[$key])) {
            throw new UserError('Unknown report.');
        }
        Auth::require($defs[$key]['perm']);
        $method = 'r_' . $key;
        return self::$method($f);
    }

    private static function year(array $f): int
    {
        if (!empty($f['year'])) {
            return (int) $f['year'];
        }
        $y = AcademicService::currentYear();
        if (!$y) {
            throw new UserError('Create an academic year first.');
        }
        return (int) $y['id'];
    }

    private static function genderLabel(?string $g): string
    {
        return ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'][$g ?? ''] ?? '';
    }

    // ---------- student reports ----------

    private static function r_students(array $f): array
    {
        $y = self::year($f);
        $rows = StudentService::searchAll($y, ['class_id' => $f['class'] ?? null, 'section_id' => $f['section'] ?? null, 'status' => $f['student_status'] ?? 'active', 'q' => $f['q'] ?? '', 'enrolled' => 'yes']);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['admission_no'], $r['sats_no'], $r['full_name'], self::genderLabel($r['gender']), $r['date_of_birth'], $r['class_name'], $r['section_name'], $r['roll_no'],
                $r['father_name'], $r['mother_name'], $r['guardian_name'], $r['phone_primary'], $r['is_active'] ? 'Active' : 'Inactive'];
        }
        return ['columns' => [['Adm. no', 'text'], ['SATS no', 'text'], ['Name', 'text'], ['Gender', 'text'], ['Date of birth', 'date'], ['Class', 'text'], ['Section', 'text'], ['Roll', 'num'],
            ['Father', 'text'], ['Mother', 'text'], ['Guardian', 'text'], ['Phone', 'text'], ['Status', 'text']], 'rows' => $out, 'summary' => ['Students' => (string) count($out)]];
    }

    private static function scopeSql(int $yearId, string $col, array &$p): string
    {
        $ids = Scope::sectionIds($yearId);
        if ($ids === null) {
            return '';
        }
        [$in, $ip] = Db::in($ids);
        $p = array_merge($p, $ip);
        return " AND $col IN ($in)";
    }

    private static function r_strength(array $f): array
    {
        $y = self::year($f);
        $p = [$y];
        $scope = self::scopeSql($y, 'e.section_id', $p);
        $rows = Db::all("SELECT c.name AS class_name, sec.name AS section_name,
                                SUM(s.gender = 'male') AS boys, SUM(s.gender = 'female') AS girls, SUM(s.gender = 'other') AS other, COUNT(*) AS total
                           FROM enrollments e JOIN students s ON s.id = e.student_id JOIN classes c ON c.id = e.class_id JOIN sections sec ON sec.id = e.section_id
                          WHERE e.academic_year_id = ? AND e.status <> 'left' AND s.is_active = 1 $scope
                          GROUP BY c.id, c.sort_order, c.name, sec.id, sec.name ORDER BY c.sort_order, sec.name", $p);
        $out = [];
        $t = 0;
        foreach ($rows as $r) {
            $out[] = [$r['class_name'], $r['section_name'], (int) $r['boys'], (int) $r['girls'], (int) $r['other'], (int) $r['total']];
            $t += (int) $r['total'];
        }
        return ['columns' => [['Class', 'text'], ['Section', 'text'], ['Boys', 'num'], ['Girls', 'num'], ['Other', 'num'], ['Total', 'num']], 'rows' => $out, 'summary' => ['Total students' => (string) $t]];
    }

    private static function r_admissions(array $f): array
    {
        $from = $f['from'] ?? date('Y-04-01');
        $to = $f['to'] ?? date('Y-m-d');
        $y = AcademicService::currentYear();
        $p = [$from, $to];
        $scope = $y ? self::scopeSql((int) $y['id'], 'e.section_id', $p) : '';
        $join = $scope !== '' ? 'JOIN' : 'LEFT JOIN';
        $rows = Db::all("SELECT s.admission_no, s.full_name, s.gender, s.admission_date, s.date_of_birth, s.phone_primary, c.name AS class_name, sec.name AS section_name
                           FROM students s $join enrollments e ON e.student_id = s.id AND e.academic_year_id = " . (int) ($y['id'] ?? 0) . "
                           LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN sections sec ON sec.id = e.section_id
                          WHERE s.admission_date BETWEEN ? AND ? $scope ORDER BY s.admission_date, s.admission_no", $p);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['admission_date'], $r['admission_no'], $r['full_name'], self::genderLabel($r['gender']), $r['date_of_birth'], $r['class_name'], $r['section_name'], $r['phone_primary']];
        }
        return ['columns' => [['Admission date', 'date'], ['Adm. no', 'text'], ['Name', 'text'], ['Gender', 'text'], ['Date of birth', 'date'], ['Class (selected year)', 'text'], ['Section', 'text'], ['Phone', 'text']],
            'rows' => $out, 'summary' => ['Admissions' => (string) count($out)]];
    }

    // ---------- finance reports ----------

    private static function r_collection(array $f): array
    {
        $flt = ['from' => $f['from'] ?? date('Y-m-01'), 'to' => $f['to'] ?? date('Y-m-d'), 'year_id' => $f['year'] ?? null, 'class_id' => $f['class'] ?? null,
            'section_id' => $f['section'] ?? null, 'category' => $f['category'] ?? null, 'method' => $f['method'] ?? null];
        $rows = FeeService::searchPayments($flt, $pg, true);
        $tot = FeeService::paymentTotals($flt);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['payment_date'], $r['receipt_no'], $r['admission_no'], $r['student_name'], $r['class_name'], $r['section_name'], FeeService::CATEGORIES[$r['category']] ?? $r['category'],
                FeeService::METHODS[$r['method']] ?? $r['method'], $r['reference_no'], Money::fromDb($r['amount']), $r['status'] === 'valid' ? 'Valid' : 'REVERSED', $r['recorded_by_name']];
        }
        return ['columns' => [['Date', 'date'], ['Receipt', 'text'], ['Adm. no', 'text'], ['Student', 'text'], ['Class', 'text'], ['Section', 'text'], ['Category', 'text'], ['Method', 'text'],
            ['Reference', 'text'], ['Amount', 'money'], ['Status', 'text'], ['Recorded by', 'text']], 'rows' => $out,
            'summary' => ['Collected (valid receipts: ' . $tot['valid']['n'] . ')' => Money::format($tot['valid']['amt']), 'Reversed (' . $tot['reversed']['n'] . ', excluded from collected)' => Money::format($tot['reversed']['amt'])]];
    }

    private static function r_pending(array $f): array
    {
        $y = self::year($f);
        $flt = ['class_id' => $f['class'] ?? null, 'section_id' => $f['section'] ?? null, 'status' => $f['fee_status'] ?? 'pending', 'sort' => 'balance'];
        $rows = FeeService::dues($y, $flt, $pg, true);
        $t = FeeService::duesTotals($y, $flt);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['admission_no'], $r['full_name'], $r['class_name'], $r['section_name'], $r['phone_primary'], $r['academic'], $r['transport'], $r['discount'], $r['net'], $r['paid'], $r['balance'], FeeService::statusLabel($r['status'])[0]];
        }
        return ['columns' => [['Adm. no', 'text'], ['Student', 'text'], ['Class', 'text'], ['Section', 'text'], ['Phone', 'text'], ['Academic', 'money'], ['Transport', 'money'], ['Discount', 'money'],
            ['Net assessed', 'money'], ['Paid', 'money'], ['Balance', 'money'], ['Status', 'text']], 'rows' => $out,
            'summary' => ['Students' => (string) $t['n'], 'Net assessed' => Money::format($t['net']), 'Paid' => Money::format($t['paid']), 'Outstanding' => Money::format($t['balance'])]];
    }

    private static function r_class_collection(array $f): array
    {
        $y = self::year($f);
        $rows = Db::all("SELECT c.id, c.name,
                (SELECT COUNT(*) FROM enrollments e WHERE e.academic_year_id = ? AND e.class_id = c.id AND e.status <> 'left') AS students,
                (SELECT COALESCE(SUM(a.amount),0) FROM student_fee_assessments a JOIN enrollments e ON e.student_id = a.student_id AND e.academic_year_id = a.academic_year_id WHERE a.academic_year_id = ? AND e.class_id = c.id AND e.status <> 'left') AS academic,
                (SELECT COALESCE(SUM(t.fee),0) FROM transport_assignments t JOIN enrollments e ON e.student_id = t.student_id AND e.academic_year_id = t.academic_year_id WHERE t.academic_year_id = ? AND t.status IN ('active','ended') AND e.class_id = c.id AND e.status <> 'left') AS transport,
                (SELECT COALESCE(SUM(d.amount),0) FROM fee_adjustments d JOIN enrollments e ON e.student_id = d.student_id AND e.academic_year_id = d.academic_year_id WHERE d.academic_year_id = ? AND d.status = 'active' AND e.class_id = c.id AND e.status <> 'left') AS discount,
                (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN enrollments e ON e.student_id = p.student_id AND e.academic_year_id = p.academic_year_id WHERE p.academic_year_id = ? AND p.status = 'valid' AND e.class_id = c.id AND e.status <> 'left') AS paid
                FROM classes c ORDER BY c.sort_order", [$y, $y, $y, $y, $y]);
        $out = [];
        $tot = ['a' => 0, 't' => 0, 'd' => 0, 'p' => 0];
        foreach ($rows as $r) {
            if ((int) $r['students'] === 0) {
                continue;
            }
            $a = Money::fromDb($r['academic']); $t = Money::fromDb($r['transport']); $d = Money::fromDb($r['discount']); $p = Money::fromDb($r['paid']);
            $out[] = [$r['name'], (int) $r['students'], $a, $t, $d, $a + $t - $d, $p, $a + $t - $d - $p];
            $tot['a'] += $a; $tot['t'] += $t; $tot['d'] += $d; $tot['p'] += $p;
        }
        return ['columns' => [['Class', 'text'], ['Students', 'num'], ['Academic', 'money'], ['Transport', 'money'], ['Discounts', 'money'], ['Net assessed', 'money'], ['Collected', 'money'], ['Pending', 'money']],
            'rows' => $out, 'summary' => ['Net assessed' => Money::format($tot['a'] + $tot['t'] - $tot['d']), 'Collected' => Money::format($tot['p']), 'Pending' => Money::format($tot['a'] + $tot['t'] - $tot['d'] - $tot['p'])]];
    }

    private static function r_year_collection(array $f): array
    {
        $out = [];
        foreach (Db::all('SELECT id, name FROM academic_years ORDER BY start_date DESC') as $y) {
            $id = (int) $y['id'];
            $a = Money::fromDb(Db::val("SELECT COALESCE(SUM(amount),0) FROM student_fee_assessments WHERE academic_year_id = ?", [$id]));
            $t = Money::fromDb(Db::val("SELECT COALESCE(SUM(fee),0) FROM transport_assignments WHERE academic_year_id = ? AND status IN ('active','ended')", [$id]));
            $d = Money::fromDb(Db::val("SELECT COALESCE(SUM(amount),0) FROM fee_adjustments WHERE academic_year_id = ? AND status = 'active'", [$id]));
            $p = Money::fromDb(Db::val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE academic_year_id = ? AND status = 'valid'", [$id]));
            $r = Money::fromDb(Db::val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE academic_year_id = ? AND status = 'reversed'", [$id]));
            $out[] = [$y['name'], $a, $t, $d, $a + $t - $d, $p, $a + $t - $d - $p, $r];
        }
        return ['columns' => [['Academic year', 'text'], ['Academic', 'money'], ['Transport', 'money'], ['Discounts', 'money'], ['Net assessed', 'money'], ['Collected', 'money'], ['Pending', 'money'], ['Reversed receipts', 'money']],
            'rows' => $out, 'summary' => []];
    }

    private static function r_bus_fees(array $f): array
    {
        $y = self::year($f);
        $rows = Db::all("SELECT b.bus_code, b.vehicle_no, b.driver_name,
                (SELECT COUNT(*) FROM transport_assignments t WHERE t.bus_id = b.id AND t.academic_year_id = ? AND t.status = 'active') AS riders,
                (SELECT COALESCE(SUM(t.fee),0) FROM transport_assignments t WHERE t.bus_id = b.id AND t.academic_year_id = ? AND t.status IN ('active','ended')) AS assessed
                FROM buses b ORDER BY b.bus_code", [$y, $y]);
        // Collected transport fees are per student; attribute them to the bus in proportion to its share of that student's fee.
        $paidByBus = [];
        $pays = Db::all("SELECT t.bus_id, t.student_id, t.fee,
                (SELECT COALESCE(SUM(fee),0) FROM transport_assignments x WHERE x.student_id = t.student_id AND x.academic_year_id = t.academic_year_id AND x.status IN ('active','ended')) AS total_fee,
                (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.student_id = t.student_id AND p.academic_year_id = t.academic_year_id AND p.category = 'transport' AND p.status = 'valid') AS paid,
                (SELECT COALESCE(SUM(d.amount),0) FROM fee_adjustments d WHERE d.student_id = t.student_id AND d.academic_year_id = t.academic_year_id AND d.category = 'transport' AND d.status = 'active') AS disc
                FROM transport_assignments t WHERE t.academic_year_id = ? AND t.status IN ('active','ended')", [$y]);
        $discByBus = [];
        foreach ($pays as $r) {
            $tf = Money::fromDb($r['total_fee']);
            if ($tf <= 0) {
                continue;
            }
            $share = Money::fromDb($r['fee']);
            $paidByBus[$r['bus_id']] = ($paidByBus[$r['bus_id']] ?? 0) + intdiv(Money::fromDb($r['paid']) * $share, $tf);
            $discByBus[$r['bus_id']] = ($discByBus[$r['bus_id']] ?? 0) + intdiv(Money::fromDb($r['disc']) * $share, $tf);
        }
        $busIds = array_column(Db::all('SELECT id, bus_code FROM buses ORDER BY bus_code'), 'id', 'bus_code');
        $out = [];
        foreach ($rows as $r) {
            $id = $busIds[$r['bus_code']] ?? 0;
            $as = Money::fromDb($r['assessed']);
            $pd = $paidByBus[$id] ?? 0;
            $dc = $discByBus[$id] ?? 0;
            if ($as === 0 && (int) $r['riders'] === 0) {
                continue;
            }
            $out[] = [$r['bus_code'], $r['vehicle_no'], $r['driver_name'], (int) $r['riders'], $as, $dc, $pd, $as - $dc - $pd];
        }
        return ['columns' => [['Bus', 'text'], ['Vehicle', 'text'], ['Driver', 'text'], ['Active riders', 'num'], ['Fee assessed', 'money'], ['Discounts (share)', 'money'], ['Collected (share)', 'money'], ['Pending', 'money']],
            'rows' => $out, 'summary' => ['Note' => 'Collected and discounts are per student; for students who changed buses they are split in proportion to each bus fee.']];
    }

    private static function r_bus_allocation(array $f): array
    {
        $y = self::year($f);
        $rows = TransportService::search($y, ['bus_id' => $f['bus'] ?? null, 'status' => 'active'], $pg, true);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['bus_code'], $r['vehicle_no'], $r['admission_no'], $r['student_name'], $r['class_name'], $r['section_name'], $r['pickup_location'], $r['distance_km'], $r['start_date'], Money::fromDb($r['fee'])];
        }
        return ['columns' => [['Bus', 'text'], ['Vehicle', 'text'], ['Adm. no', 'text'], ['Student', 'text'], ['Class', 'text'], ['Section', 'text'], ['Pickup / drop', 'text'], ['Distance (km)', 'num'], ['Since', 'date'], ['Fee', 'money']],
            'rows' => $out, 'summary' => ['Students on buses' => (string) count($out)]];
    }

    // ---------- academic reports ----------

    private static function r_results(array $f): array
    {
        $examId = (int) ($f['exam'] ?? 0);
        $classId = (int) ($f['class'] ?? 0);
        if (!$examId || !$classId) {
            throw new UserError('Choose an exam and a class.');
        }
        $d = ExamService::classResults($examId, $classId, !empty($f['section']) ? (int) $f['section'] : null);
        $cols = [['Roll', 'num'], ['Adm. no', 'text'], ['Student', 'text'], ['Section', 'text']];
        foreach ($d['papers'] as $p) {
            $cols[] = [$p['subject_name'] . ' (/' . Marks::display($p['max_marks']) . ')', 'text'];
        }
        array_push($cols, ['Total', 'text'], ['Percentage', 'text'], ['Result', 'text']);
        $out = [];
        $pass = 0;
        $fail = 0;
        foreach ($d['students'] as $s) {
            $row = [$s['roll_no'], $s['admission_no'], $s['full_name'], $s['section_name']];
            foreach ($d['papers'] as $p) {
                $c = $s['cells'][$p['id']];
                $row[] = $c['eval']['state'] === 'absent' ? 'AB' : ($c['eval']['state'] === 'missing' ? '-' : Marks::display($c['scored']) . ($c['eval']['result'] === 'Fail' ? ' (F)' : ''));
            }
            $o = $s['overall'];
            $row[] = $o['max'] > 0 ? Marks::display((string) $o['total']) . '/' . Marks::display((string) $o['max']) : '';
            $row[] = $o['percentage'] !== null ? number_format($o['percentage'], 2) . '%' : '';
            $row[] = $o['result'];
            $pass += $o['result'] === 'Pass' ? 1 : 0;
            $fail += $o['result'] === 'Fail' ? 1 : 0;
            $out[] = $row;
        }
        return ['columns' => $cols, 'rows' => $out, 'summary' => ['Exam' => $d['exam']['name'] . ' (' . $d['exam']['year_name'] . ')', 'Pass' => (string) $pass, 'Fail' => (string) $fail,
            'Note' => 'AB = absent, - = marks not entered, (F) = below passing marks. Overall counts only entered subjects; Incomplete means some marks are missing.']];
    }

    private static function r_subject_results(array $f): array
    {
        $examId = (int) ($f['exam'] ?? 0);
        $classId = (int) ($f['class'] ?? 0);
        $subjectId = (int) ($f['subject'] ?? 0);
        if (!$examId || !$classId || !$subjectId) {
            throw new UserError('Choose an exam, a class and a subject.');
        }
        $d = ExamService::classResults($examId, $classId, !empty($f['section']) ? (int) $f['section'] : null);
        $paper = null;
        foreach ($d['papers'] as $p) {
            if ((int) $p['subject_id'] === $subjectId) {
                $paper = $p;
            }
        }
        if (!$paper) {
            throw new UserError('No paper has been set up for that subject, exam and class.');
        }
        $out = [];
        $pass = $fail = $abs = $miss = 0;
        foreach ($d['students'] as $s) {
            $c = $s['cells'][$paper['id']];
            $e = $c['eval'];
            $out[] = [$s['roll_no'], $s['admission_no'], $s['full_name'], $s['section_name'], $e['state'] === 'entered' ? Marks::display($c['scored']) : ($e['state'] === 'absent' ? 'AB' : ''),
                $e['percentage'] !== null ? number_format($e['percentage'], 2) . '%' : '', $e['result']];
            $pass += $e['result'] === 'Pass' ? 1 : 0; $fail += $e['result'] === 'Fail' ? 1 : 0; $abs += $e['state'] === 'absent' ? 1 : 0; $miss += $e['state'] === 'missing' ? 1 : 0;
        }
        return ['columns' => [['Roll', 'num'], ['Adm. no', 'text'], ['Student', 'text'], ['Section', 'text'], ['Marks (/' . Marks::display($paper['max_marks']) . ')', 'text'], ['Percentage', 'text'], ['Result', 'text']],
            'rows' => $out, 'summary' => ['Subject' => $paper['subject_name'] . ' - passing marks ' . Marks::display($paper['min_marks']), 'Pass' => (string) $pass, 'Fail' => (string) $fail, 'Absent' => (string) $abs, 'Not entered' => (string) $miss]];
    }

    // ---------- staff ----------

    private static function r_staff(array $f): array
    {
        $st = $f['staff_status'] ?? 'active';
        $where = $st === 'active' ? 'WHERE e.is_active = 1' : ($st === 'inactive' ? 'WHERE e.is_active = 0' : '');
        $rows = Db::all("SELECT e.*, (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM employee_subjects es JOIN subjects s ON s.id = es.subject_id WHERE es.employee_id = e.id) AS subjects,
                         (SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.employee_id = e.id) AS login_role
                         FROM employees e $where ORDER BY e.full_name");
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['employee_code'], $r['full_name'], $r['designation'], $r['phone'], $r['email'], $r['start_date'], $r['subjects'], $r['login_role'] ?: 'No login', $r['is_active'] ? 'Active' : 'Inactive'];
        }
        return ['columns' => [['Employee ID', 'text'], ['Name', 'text'], ['Designation', 'text'], ['Phone', 'text'], ['Email', 'text'], ['Since', 'date'], ['Subjects', 'text'], ['Login', 'text'], ['Status', 'text']],
            'rows' => $out, 'summary' => ['Employees' => (string) count($out)]];
    }
}
