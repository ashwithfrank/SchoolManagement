<?php
namespace App\Services;

use App\Core\{Auth, Db, Settings};

/**
 * Data scope for Teaching Staff: they see students of sections they are assigned to teach
 * or coordinate (in the selected year). Admin and Office are not restricted by section.
 */
class Scope
{
    public static function restricted(): bool
    {
        return Auth::role() === 'teaching';
    }

    public static function employeeId(): ?int
    {
        $u = Auth::user();
        return $u && $u['employee_id'] ? (int) $u['employee_id'] : null;
    }

    /** Section ids the current user may work with in a year; null means "all". */
    public static function sectionIds(int $yearId): ?array
    {
        if (!self::restricted()) {
            return null;
        }
        $emp = self::employeeId();
        if (!$emp) {
            return [];
        }
        $rows = Db::all('SELECT section_id FROM teaching_assignments WHERE academic_year_id = ? AND employee_id = ?
                         UNION SELECT section_id FROM section_coordinators WHERE academic_year_id = ? AND employee_id = ?',
            [$yearId, $emp, $yearId, $emp]);
        return array_map('intval', array_column($rows, 'section_id'));
    }

    public static function canSeeStudent(int $studentId, int $yearId): bool
    {
        $ids = self::sectionIds($yearId);
        if ($ids === null) {
            return true;
        }
        if (!$ids) {
            return false;
        }
        [$in, $p] = Db::in($ids);
        return (bool) Db::val("SELECT 1 FROM enrollments WHERE student_id = ? AND academic_year_id = ? AND section_id IN ($in) LIMIT 1", array_merge([$studentId, $yearId], $p));
    }

    /** May the current user enter marks for this section + subject in this year? */
    public static function canMark(int $yearId, int $sectionId, int $subjectId): bool
    {
        if (!Auth::can('marks.enter')) {
            return false;
        }
        if (!self::restricted() || Settings::get('teachers_all_marks') === '1') {
            return true;
        }
        $emp = self::employeeId();
        if (!$emp) {
            return false;
        }
        return (bool) Db::val('SELECT 1 FROM teaching_assignments WHERE academic_year_id = ? AND section_id = ? AND subject_id = ? AND employee_id = ?',
            [$yearId, $sectionId, $subjectId, $emp]);
    }

    /** Subject ids this user may touch for a class in a year (null = all). Used to limit paper editing. */
    public static function subjectIdsForClass(int $yearId, int $classId): ?array
    {
        if (!self::restricted() || Settings::get('teachers_all_marks') === '1') {
            return null;
        }
        $emp = self::employeeId();
        if (!$emp) {
            return [];
        }
        $rows = Db::all('SELECT DISTINCT ta.subject_id FROM teaching_assignments ta JOIN sections s ON s.id = ta.section_id
                          WHERE ta.academic_year_id = ? AND ta.employee_id = ? AND s.class_id = ?', [$yearId, $emp, $classId]);
        return array_map('intval', array_column($rows, 'subject_id'));
    }
}
