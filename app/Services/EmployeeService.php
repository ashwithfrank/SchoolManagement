<?php
namespace App\Services;

use App\Core\{Audit, Auth, Db, Pager, UserError, Validator};

class EmployeeService
{
    public static function nextCode(): string
    {
        Db::exec("INSERT INTO counters (name, value) VALUES ('employee', 0) ON DUPLICATE KEY UPDATE value = value");
        $n = (int) Db::val("SELECT value FROM counters WHERE name = 'employee' FOR UPDATE") + 1;
        Db::exec("UPDATE counters SET value = ? WHERE name = 'employee'", [$n]);
        return 'EMP-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function find(int $id): ?array
    {
        return Db::row('SELECT * FROM employees WHERE id = ?', [$id]);
    }

    public static function search(string $q, string $status, ?Pager &$pager = null): array
    {
        Auth::require('employees.view');
        $where = ['1=1'];
        $p = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(full_name LIKE ? OR employee_code LIKE ? OR phone LIKE ? OR designation LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }
        $w = implode(' AND ', $where);
        $pager = new Pager((int) Db::val("SELECT COUNT(*) FROM employees WHERE $w", $p), 20);
        return Db::all("SELECT e.*, (SELECT COUNT(*) FROM users u WHERE u.employee_id = e.id) AS has_login
                          FROM employees e WHERE $w ORDER BY full_name" . $pager->limitSql(), $p);
    }

    public static function activeList(): array
    {
        return Db::all('SELECT id, employee_code, full_name, designation FROM employees WHERE is_active = 1 ORDER BY full_name');
    }

    public static function save(?int $id, array $input): int
    {
        Auth::require('employees.manage');
        $v = new Validator($input);
        $name = $v->text('full_name', 'Name', true, 120);
        $phone = $v->phone('phone', 'Contact number');
        $email = $v->email('email', 'Email');
        $addr = $v->text('address', 'Address', false, 300);
        $desig = $v->text('designation', 'Designation', false, 80);
        $start = $v->date('start_date', 'Employment start date');
        $v->check();
        if ($id) {
            if (!self::find($id)) {
                throw new UserError('Employee not found.');
            }
            Db::exec('UPDATE employees SET full_name=?, phone=?, email=?, address=?, designation=?, start_date=?, updated_at=NOW() WHERE id=?',
                [$name, $phone, $email, $addr, $desig, $start, $id]);
            Audit::log('employee.update', 'employee', $id, ['name' => $name]);
            return $id;
        }
        return Db::tx(function () use ($name, $phone, $email, $addr, $desig, $start) {
            $code = self::nextCode();
            $id = Db::insert('INSERT INTO employees (employee_code, full_name, phone, email, address, designation, start_date, is_active, created_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())', [$code, $name, $phone, $email, $addr, $desig, $start]);
            Audit::log('employee.create', 'employee', $id, ['code' => $code, 'name' => $name]);
            return $id;
        });
    }

    public static function setActive(int $id, bool $active): void
    {
        Auth::require('employees.manage');
        Db::exec('UPDATE employees SET is_active = ?, updated_at = NOW() WHERE id = ?', [$active ? 1 : 0, $id]);
        Audit::log($active ? 'employee.activate' : 'employee.deactivate', 'employee', $id);
    }

    // ---- subjects an employee can teach ----

    public static function subjectIds(int $id): array
    {
        return array_map('intval', array_column(Db::all('SELECT subject_id FROM employee_subjects WHERE employee_id = ?', [$id]), 'subject_id'));
    }

    public static function setSubjects(int $id, array $subjectIds): void
    {
        Auth::require('employees.manage');
        $ids = array_values(array_unique(array_filter(array_map('intval', $subjectIds))));
        Db::tx(function () use ($id, $ids) {
            Db::exec('DELETE FROM employee_subjects WHERE employee_id = ?', [$id]);
            foreach ($ids as $sid) {
                Db::exec('INSERT INTO employee_subjects (employee_id, subject_id) SELECT ?, id FROM subjects WHERE id = ?', [$id, $sid]);
            }
            Audit::log('employee.subjects', 'employee', $id, ['subjects' => $ids]);
        });
    }

    // ---- class/section/subject assignments (per academic year) ----

    public static function assignments(int $employeeId, ?int $yearId = null): array
    {
        $sql = 'SELECT ta.*, s.name AS section_name, c.name AS class_name, sub.name AS subject_name, y.name AS year_name
                  FROM teaching_assignments ta
                  JOIN sections s ON s.id = ta.section_id JOIN classes c ON c.id = s.class_id
                  JOIN subjects sub ON sub.id = ta.subject_id JOIN academic_years y ON y.id = ta.academic_year_id
                 WHERE ta.employee_id = ?';
        $p = [$employeeId];
        if ($yearId) {
            $sql .= ' AND ta.academic_year_id = ?';
            $p[] = $yearId;
        }
        return Db::all($sql . ' ORDER BY y.start_date DESC, c.sort_order, s.name, sub.name', $p);
    }

    public static function addAssignment(int $employeeId, int $yearId, int $sectionId, int $subjectId): void
    {
        Auth::require('employees.manage');
        $emp = self::find($employeeId);
        if (!$emp || !$emp['is_active']) {
            throw new UserError('Choose an active employee.');
        }
        try {
            Db::exec('INSERT INTO teaching_assignments (academic_year_id, employee_id, section_id, subject_id) VALUES (?, ?, ?, ?)',
                [$yearId, $employeeId, $sectionId, $subjectId]);
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                throw new UserError('That section and subject already has a teacher for this year.');
            }
            if (($e->errorInfo[1] ?? 0) === 1452) {
                throw new UserError('Year, section or subject not found.');
            }
            throw $e;
        }
        Audit::log('assignment.add', 'employee', $employeeId, compact('yearId', 'sectionId', 'subjectId'));
    }

    public static function removeAssignment(int $id): void
    {
        Auth::require('employees.manage');
        Db::exec('DELETE FROM teaching_assignments WHERE id = ?', [$id]);
        Audit::log('assignment.remove', 'teaching_assignment', $id);
    }

    public static function coordinatorOf(int $employeeId): array
    {
        return Db::all('SELECT sc.*, s.name AS section_name, c.name AS class_name, y.name AS year_name
                          FROM section_coordinators sc JOIN sections s ON s.id = sc.section_id JOIN classes c ON c.id = s.class_id
                          JOIN academic_years y ON y.id = sc.academic_year_id
                         WHERE sc.employee_id = ? ORDER BY y.start_date DESC, c.sort_order', [$employeeId]);
    }
}
