<?php
namespace App\Services;

use App\Core\{Audit, Auth, Db, Dates, UserError};

/** Academic years, classes, sections, subjects and section coordinators. */
class AcademicService
{
    // ---------- years ----------

    public static function years(): array
    {
        return Db::all('SELECT * FROM academic_years ORDER BY start_date DESC');
    }

    public static function year(int $id): ?array
    {
        return Db::row('SELECT * FROM academic_years WHERE id = ?', [$id]);
    }

    public static function activeYear(): ?array
    {
        return Db::row('SELECT * FROM academic_years WHERE active_flag = 1');
    }

    /** The year the user is working in: their selection, else the active year, else the newest. */
    public static function currentYear(): ?array
    {
        $sel = $_SESSION['year_id'] ?? null;
        if ($sel && ($y = self::year((int) $sel))) {
            return $y;
        }
        return self::activeYear() ?: Db::row('SELECT * FROM academic_years ORDER BY start_date DESC LIMIT 1');
    }

    public static function selectYear(int $id): void
    {
        if (self::year($id)) {
            $_SESSION['year_id'] = $id;
        }
    }

    public static function createYear(string $name, string $start, string $end, bool $makeActive = false): int
    {
        Auth::require('academics.manage');
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 20) {
            throw new UserError('Enter a year name such as 2026-27.');
        }
        if (!Dates::isValid($start) || !Dates::isValid($end) || $end <= $start) {
            throw new UserError('The end date must be after the start date.');
        }
        return Db::tx(function () use ($name, $start, $end, $makeActive) {
            try {
                $id = Db::insert('INSERT INTO academic_years (name, start_date, end_date, created_at) VALUES (?, ?, ?, NOW())', [$name, $start, $end]);
            } catch (\PDOException $e) {
                if (Db::isDuplicate($e)) {
                    throw new UserError("An academic year named $name already exists.");
                }
                throw $e;
            }
            Audit::log('year.create', 'academic_year', $id, ['name' => $name]);
            if ($makeActive) {
                self::setActive($id);
            }
            return $id;
        });
    }

    public static function setActive(int $id): void
    {
        Auth::require('academics.manage');
        if (!self::year($id)) {
            throw new UserError('Academic year not found.');
        }
        Db::tx(function () use ($id) {
            Db::exec('UPDATE academic_years SET active_flag = NULL WHERE active_flag = 1');
            Db::exec('UPDATE academic_years SET active_flag = 1 WHERE id = ?', [$id]);
            Audit::log('year.activate', 'academic_year', $id);
        });
        $_SESSION['year_id'] = $id;
    }

    // ---------- classes & sections ----------

    public static function classes(): array
    {
        return Db::all('SELECT * FROM classes ORDER BY sort_order');
    }

    public static function classRow(int $id): ?array
    {
        return Db::row('SELECT * FROM classes WHERE id = ?', [$id]);
    }

    public static function sections(?int $classId = null, bool $activeOnly = true): array
    {
        $sql = 'SELECT s.*, c.name AS class_name, c.sort_order FROM sections s JOIN classes c ON c.id = s.class_id WHERE 1=1';
        $p = [];
        if ($classId) {
            $sql .= ' AND s.class_id = ?';
            $p[] = $classId;
        }
        if ($activeOnly) {
            $sql .= ' AND s.is_active = 1';
        }
        return Db::all($sql . ' ORDER BY c.sort_order, s.name', $p);
    }

    public static function section(int $id): ?array
    {
        return Db::row('SELECT s.*, c.name AS class_name FROM sections s JOIN classes c ON c.id = s.class_id WHERE s.id = ?', [$id]);
    }

    public static function addSection(int $classId, string $name): int
    {
        Auth::require('academics.manage');
        $name = strtoupper(trim($name));
        if ($name === '' || mb_strlen($name) > 20 || !preg_match('/^[A-Z0-9 -]+$/', $name)) {
            throw new UserError('Section name must be letters/numbers, for example A, B or Blue.');
        }
        if (!self::classRow($classId)) {
            throw new UserError('Class not found.');
        }
        try {
            $id = Db::insert('INSERT INTO sections (class_id, name, is_active) VALUES (?, ?, 1)', [$classId, $name]);
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                throw new UserError('That section already exists for the class.');
            }
            throw $e;
        }
        Audit::log('section.create', 'section', $id, ['class_id' => $classId, 'name' => $name]);
        return $id;
    }

    public static function setSectionActive(int $id, bool $active): void
    {
        Auth::require('academics.manage');
        Db::exec('UPDATE sections SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
        Audit::log($active ? 'section.enable' : 'section.disable', 'section', $id);
    }

    // ---------- subjects ----------

    public static function subjects(bool $activeOnly = false): array
    {
        return Db::all('SELECT * FROM subjects' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name');
    }

    public static function saveSubject(?int $id, string $name, ?string $code): int
    {
        Auth::require('academics.manage');
        $name = trim($name);
        $code = $code === null || trim($code) === '' ? null : strtoupper(trim($code));
        if ($name === '' || mb_strlen($name) > 80) {
            throw new UserError('Enter a subject name.');
        }
        if ($code !== null && !preg_match('/^[A-Z0-9_-]{1,20}$/', $code)) {
            throw new UserError('Subject code may contain letters, numbers, - and _ only.');
        }
        try {
            if ($id) {
                Db::exec('UPDATE subjects SET name = ?, code = ? WHERE id = ?', [$name, $code, $id]);
                Audit::log('subject.update', 'subject', $id, ['name' => $name]);
                return $id;
            }
            $id = Db::insert('INSERT INTO subjects (name, code, is_active) VALUES (?, ?, 1)', [$name, $code]);
            Audit::log('subject.create', 'subject', $id, ['name' => $name]);
            return $id;
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                throw new UserError('A subject with that name or code already exists.');
            }
            throw $e;
        }
    }

    public static function setSubjectActive(int $id, bool $active): void
    {
        Auth::require('academics.manage');
        Db::exec('UPDATE subjects SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
        Audit::log($active ? 'subject.enable' : 'subject.disable', 'subject', $id);
    }

    // ---------- coordinators ----------

    public static function coordinators(int $yearId): array
    {
        return Db::all('SELECT sc.*, s.name AS section_name, c.name AS class_name, e.full_name AS employee_name, e.employee_code
                          FROM section_coordinators sc
                          JOIN sections s ON s.id = sc.section_id
                          JOIN classes c ON c.id = s.class_id
                          JOIN employees e ON e.id = sc.employee_id
                         WHERE sc.academic_year_id = ? ORDER BY c.sort_order, s.name', [$yearId]);
    }

    public static function setCoordinator(int $yearId, int $sectionId, int $employeeId): void
    {
        Auth::require('academics.manage');
        if (!self::year($yearId) || !self::section($sectionId)) {
            throw new UserError('Year or section not found.');
        }
        $emp = Db::row('SELECT id, is_active FROM employees WHERE id = ?', [$employeeId]);
        if (!$emp || !$emp['is_active']) {
            throw new UserError('Choose an active employee.');
        }
        Db::exec('INSERT INTO section_coordinators (academic_year_id, section_id, employee_id) VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE employee_id = VALUES(employee_id)', [$yearId, $sectionId, $employeeId]);
        Audit::log('coordinator.set', 'section', $sectionId, ['year_id' => $yearId, 'employee_id' => $employeeId]);
    }

    public static function removeCoordinator(int $id): void
    {
        Auth::require('academics.manage');
        Db::exec('DELETE FROM section_coordinators WHERE id = ?', [$id]);
        Audit::log('coordinator.remove', 'section_coordinator', $id);
    }
}
