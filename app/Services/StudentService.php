<?php
namespace App\Services;

use App\Core\{Audit, Auth, Db, Pager, UserError, Validator};

class StudentService
{
    public const SORTS = [
        'name'      => 's.full_name',
        'admission' => 's.admission_no',
        'class'     => 'c.sort_order, sec.name, s.full_name',
        'newest'    => 's.id',
    ];

    public static function likeTerm(string $q): string
    {
        return '%' . addcslashes($q, '%_\\') . '%';
    }

    public static function find(int $id): ?array
    {
        return Db::row('SELECT * FROM students WHERE id = ?', [$id]);
    }

    /**
     * Students list for one academic year (enrollment columns are for that year).
     * Filters: q, class_id, section_id, status (active|inactive|all), enrolled (all|yes|no), sort, dir.
     */
    public static function search(int $yearId, array $f, int $perPage = 20, ?Pager &$pager = null): array
    {
        Auth::require('students.view');
        [$sql, $p] = self::searchSql($yearId, $f);
        $total = (int) Db::val('SELECT COUNT(*) FROM (' . $sql . ') t', $p);
        $pager = new Pager($total, $perPage);
        $sortKey = $f['sort'] ?? 'name';
        $order = self::SORTS[$sortKey] ?? self::SORTS['name'];
        if (($f['dir'] ?? 'asc') === 'desc') {
            $order = implode(', ', array_map(fn ($c) => trim($c) . ' DESC', explode(',', $order)));
        }
        return Db::all($sql . ' ORDER BY ' . $order . $pager->limitSql(), $p);
    }

    /** All matching rows without paging (CSV export / reports). */
    public static function searchAll(int $yearId, array $f): array
    {
        Auth::require('students.view');
        [$sql, $p] = self::searchSql($yearId, $f);
        return Db::all($sql . ' ORDER BY c.sort_order, sec.name, s.full_name', $p);
    }

    private static function searchSql(int $yearId, array $f): array
    {
        $scope = Scope::sectionIds($yearId);
        $join = ($scope !== null || ($f['enrolled'] ?? 'all') === 'yes') ? 'JOIN' : 'LEFT JOIN';
        $sql = "SELECT s.id, s.student_code, s.admission_no, s.sats_no, s.full_name, s.gender, s.date_of_birth, s.admission_date,
                       s.father_name, s.mother_name, s.guardian_name, s.phone_primary, s.is_active, s.photo_file,
                       e.id AS enrollment_id, e.roll_no, e.status AS enroll_status, e.class_id, e.section_id,
                       c.name AS class_name, sec.name AS section_name
                  FROM students s
                  $join enrollments e ON e.student_id = s.id AND e.academic_year_id = ?
                  LEFT JOIN classes c ON c.id = e.class_id
                  LEFT JOIN sections sec ON sec.id = e.section_id
                 WHERE 1=1";
        $p = [$yearId];
        if ($scope !== null) {
            [$in, $ip] = Db::in($scope);
            $sql .= " AND e.section_id IN ($in)";
            $p = array_merge($p, $ip);
        }
        if (!empty($f['q'])) {
            $like = self::likeTerm($f['q']);
            $sql .= ' AND (s.full_name LIKE ? OR s.admission_no LIKE ? OR s.sats_no LIKE ? OR s.student_code LIKE ? OR s.phone_primary LIKE ? OR s.father_name LIKE ? OR s.mother_name LIKE ?)';
            array_push($p, $like, $like, $like, $like, $like, $like, $like);
        }
        if (!empty($f['class_id'])) {
            $sql .= ' AND e.class_id = ?';
            $p[] = (int) $f['class_id'];
        }
        if (!empty($f['section_id'])) {
            $sql .= ' AND e.section_id = ?';
            $p[] = (int) $f['section_id'];
        }
        $status = $f['status'] ?? 'active';
        if ($status === 'active') {
            $sql .= ' AND s.is_active = 1';
        } elseif ($status === 'inactive') {
            $sql .= ' AND s.is_active = 0';
        }
        if (($f['enrolled'] ?? 'all') === 'no') {
            $sql .= ' AND e.id IS NULL';
        }
        if (!empty($f['admitted_from'])) {
            $sql .= ' AND s.admission_date >= ?';
            $p[] = $f['admitted_from'];
        }
        if (!empty($f['admitted_to'])) {
            $sql .= ' AND s.admission_date <= ?';
            $p[] = $f['admitted_to'];
        }
        return [$sql, $p];
    }

    private static function nextCode(): string
    {
        Db::exec("INSERT INTO counters (name, value) VALUES ('student', 0) ON DUPLICATE KEY UPDATE value = value");
        $n = (int) Db::val("SELECT value FROM counters WHERE name = 'student' FOR UPDATE") + 1;
        Db::exec("UPDATE counters SET value = ? WHERE name = 'student'", [$n]);
        return 'S-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    /** Validate and normalise the form. Returns the clean column map; throws UserError. */
    public static function validate(array $in, ?int $existingId = null): array
    {
        $v = new Validator($in);
        $d = [];
        $d['admission_no'] = $v->text('admission_no', 'Admission number', true, 30);
        if ($d['admission_no'] !== null && !preg_match('/^[A-Za-z0-9\/._-]+$/', $d['admission_no'])) {
            $v->error('admission_no', 'Admission number may contain letters, numbers, / . _ - only.');
        }
        $d['sats_no'] = $v->text('sats_no', 'SATS number', false, 30);
        $d['full_name'] = $v->text('full_name', 'Student name', true, 120);
        $d['gender'] = $v->in('gender', 'Gender', ['male', 'female', 'other']);
        $d['date_of_birth'] = $v->date('date_of_birth', 'Date of birth', true);
        $d['admission_date'] = $v->date('admission_date', 'Date of admission', true);
        if ($d['date_of_birth'] && $d['date_of_birth'] > date('Y-m-d')) {
            $v->error('date_of_birth', 'Date of birth cannot be in the future.');
        }
        if ($d['date_of_birth'] && $d['admission_date'] && $d['admission_date'] < $d['date_of_birth']) {
            $v->error('admission_date', 'Date of admission cannot be before the date of birth.');
        }
        $d['father_name'] = $v->text('father_name', 'Father name', false, 120);
        $d['mother_name'] = $v->text('mother_name', 'Mother name', false, 120);
        $d['guardian_name'] = $v->text('guardian_name', 'Guardian name', false, 120);
        if (!$d['father_name'] && !$d['mother_name'] && !$d['guardian_name']) {
            $v->error('father_name', 'Enter at least one of father, mother or guardian name.');
        }
        $d['phone_primary'] = $v->phone('phone_primary', 'Primary contact number', true);
        $d['phone_secondary'] = $v->phone('phone_secondary', 'Secondary contact number');
        $d['email'] = $v->email('email', 'Email');
        foreach (['addr_house' => 'House/building', 'addr_street' => 'Street', 'addr_locality' => 'Locality', 'addr_village' => 'Village/town',
                  'addr_post_office' => 'Post office', 'addr_taluk' => 'Taluk', 'addr_district' => 'District', 'addr_state' => 'State'] as $k => $label) {
            $d[$k] = $v->text($k, $label, false, 100);
        }
        $d['addr_pin'] = $v->pin('addr_pin', 'PIN code');
        $d['notes'] = $v->text('notes', 'Notes', false, 2000);
        $v->check();
        // uniqueness (friendly message before the database constraint)
        $dup = Db::val('SELECT id FROM students WHERE admission_no = ? AND id <> ?', [$d['admission_no'], $existingId ?? 0]);
        if ($dup) {
            throw new UserError('Admission number ' . $d['admission_no'] . ' is already used by another student.');
        }
        if ($d['sats_no'] !== null && Db::val('SELECT id FROM students WHERE sats_no = ? AND id <> ?', [$d['sats_no'], $existingId ?? 0])) {
            throw new UserError('SATS number ' . $d['sats_no'] . ' is already used by another student.');
        }
        return $d;
    }

    /** Teaching staff may update contact, guardian and address details only. */
    private const TEACHER_FIELDS = ['father_name', 'mother_name', 'guardian_name', 'phone_primary', 'phone_secondary', 'email',
        'addr_house', 'addr_street', 'addr_locality', 'addr_village', 'addr_post_office', 'addr_taluk', 'addr_district', 'addr_state', 'addr_pin'];

    public static function create(array $in, ?array $enrol = null): int
    {
        Auth::require('students.manage');
        $d = self::validate($in);
        $yearId = $enrol['year_id'] ?? null;
        $classId = $enrol['class_id'] ?? null;
        $sectionId = $enrol['section_id'] ?? null;
        if (($classId || $sectionId) && (!$yearId || !$classId || !$sectionId)) {
            throw new UserError('Choose both a class and a section, or leave both empty.');
        }
        return Db::tx(function () use ($d, $yearId, $classId, $sectionId, $enrol) {
            $code = self::nextCode();
            $d['student_code'] = $code;
            $d['created_by'] = Auth::id();
            $cols = array_keys($d);
            $sql = 'INSERT INTO students (' . implode(',', $cols) . ', created_at, updated_at) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ', NOW(), NOW())';
            try {
                $id = Db::insert($sql, array_values($d));
            } catch (\PDOException $e) {
                if (Db::isDuplicate($e)) {
                    throw new UserError('Admission number or SATS number already exists.');
                }
                throw $e;
            }
            Audit::log('student.create', 'student', $id, ['code' => $code, 'admission_no' => $d['admission_no'], 'name' => $d['full_name']]);
            if ($classId) {
                EnrollmentService::enroll($id, (int) $yearId, (int) $classId, (int) $sectionId, $enrol['roll_no'] ?? null);
            }
            return $id;
        });
    }

    public static function update(int $id, array $in): void
    {
        $old = self::find($id);
        if (!$old) {
            throw new UserError('Student not found.');
        }
        $year = AcademicService::currentYear();
        if (Auth::can('students.manage')) {
            $d = self::validate($in, $id);
        } elseif (Auth::role() === 'teaching' && Auth::can('students.view') && $year && Scope::canSeeStudent($id, (int) $year['id'])) {
            // Teachers: only contact/guardian/address fields change; everything else keeps its stored value.
            $merged = array_merge($old, array_intersect_key($in, array_flip(self::TEACHER_FIELDS)));
            foreach (['date_of_birth', 'admission_date'] as $k) {
                $merged[$k] = substr((string) $old[$k], 0, 10);
            }
            $d = self::validate($merged, $id);
            $d = array_intersect_key($d, array_flip(self::TEACHER_FIELDS));
        } else {
            Auth::require('students.manage');
            return;
        }
        $sets = [];
        $p = [];
        $changed = [];
        foreach ($d as $k => $val) {
            $sets[] = "$k = ?";
            $p[] = $val;
            if ((string) ($old[$k] ?? '') !== (string) ($val ?? '')) {
                $changed[] = $k;
            }
        }
        $p[] = $id;
        try {
            Db::exec('UPDATE students SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?', $p);
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                throw new UserError('Admission number or SATS number already exists.');
            }
            throw $e;
        }
        Audit::log('student.update', 'student', $id, ['changed' => $changed]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Auth::require('students.manage');
        Db::exec('UPDATE students SET is_active = ?, updated_at = NOW() WHERE id = ?', [$active ? 1 : 0, $id]);
        Audit::log($active ? 'student.activate' : 'student.deactivate', 'student', $id);
    }

    /** Can the current user open this student's profile? */
    public static function canView(int $id): bool
    {
        if (!Auth::can('students.view')) {
            return false;
        }
        $y = AcademicService::currentYear();
        return !Scope::restricted() || ($y && Scope::canSeeStudent($id, (int) $y['id']));
    }

    public static function enrollments(int $studentId): array
    {
        return Db::all('SELECT e.*, y.name AS year_name, c.name AS class_name, sec.name AS section_name
                          FROM enrollments e JOIN academic_years y ON y.id = e.academic_year_id
                          JOIN classes c ON c.id = e.class_id JOIN sections sec ON sec.id = e.section_id
                         WHERE e.student_id = ? ORDER BY y.start_date DESC', [$studentId]);
    }

    public static function enrollmentFor(int $studentId, int $yearId): ?array
    {
        return Db::row('SELECT e.*, c.name AS class_name, sec.name AS section_name
                          FROM enrollments e JOIN classes c ON c.id = e.class_id JOIN sections sec ON sec.id = e.section_id
                         WHERE e.student_id = ? AND e.academic_year_id = ?', [$studentId, $yearId]);
    }

    /** Short label for pickers: "Name (Adm 123, Class 5-A)". */
    public static function quickSearch(string $q, int $yearId, int $limit = 15): array
    {
        Auth::require('students.view');
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = self::likeTerm($q);
        return Db::all('SELECT s.id, s.full_name, s.admission_no, c.name AS class_name, sec.name AS section_name
                          FROM students s
                          LEFT JOIN enrollments e ON e.student_id = s.id AND e.academic_year_id = ?
                          LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN sections sec ON sec.id = e.section_id
                         WHERE s.is_active = 1 AND (s.full_name LIKE ? OR s.admission_no LIKE ?) ORDER BY s.full_name LIMIT ' . (int) $limit,
            [$yearId, $like, $like]);
    }
}
