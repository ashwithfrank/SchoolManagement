<?php
namespace App\Services;

use App\Core\{Audit, Auth, Db, UserError};

/** Year-specific enrollment and bulk promotion. Student identity lives in `students`; history stays here. */
class EnrollmentService
{
    public static function enroll(int $studentId, int $yearId, int $classId, int $sectionId, ?int $rollNo = null, ?int $promotedFromId = null): int
    {
        Auth::requireAny(['students.manage', 'promotion.manage']);
        $sec = AcademicService::section($sectionId);
        if (!$sec || (int) $sec['class_id'] !== $classId) {
            throw new UserError('The selected section does not belong to the selected class.');
        }
        if (!$sec['is_active']) {
            throw new UserError('That section is not active.');
        }
        if (!AcademicService::year($yearId)) {
            throw new UserError('Academic year not found.');
        }
        $stu = StudentService::find($studentId);
        if (!$stu) {
            throw new UserError('Student not found.');
        }
        return Db::tx(function () use ($studentId, $yearId, $classId, $sectionId, $rollNo, $promotedFromId, $stu) {
            try {
                $id = Db::insert('INSERT INTO enrollments (student_id, academic_year_id, class_id, section_id, roll_no, status, promoted_from_id, created_by, created_at, updated_at)
                                  VALUES (?, ?, ?, ?, ?, \'active\', ?, ?, NOW(), NOW())',
                    [$studentId, $yearId, $classId, $sectionId, $rollNo ?: null, $promotedFromId, Auth::id()]);
            } catch (\PDOException $e) {
                if (Db::isDuplicate($e)) {
                    $m = (string) $e->getMessage();
                    throw new UserError(strpos($m, 'uq_enroll_roll') !== false
                        ? 'That roll number is already used in this section.'
                        : $stu['full_name'] . ' is already enrolled in this academic year.');
                }
                throw $e;
            }
            Audit::log('enrollment.create', 'enrollment', $id, ['student_id' => $studentId, 'year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId]);
            FeeService::ensureAssessment($studentId, $yearId, $classId);
            return $id;
        });
    }

    /** Move a student's enrollment to another section/class in the same year, or change the roll number. */
    public static function change(int $enrollmentId, int $classId, int $sectionId, ?int $rollNo): void
    {
        Auth::require('students.manage');
        $e = Db::row('SELECT * FROM enrollments WHERE id = ?', [$enrollmentId]);
        if (!$e) {
            throw new UserError('Enrollment not found.');
        }
        $sec = AcademicService::section($sectionId);
        if (!$sec || (int) $sec['class_id'] !== $classId) {
            throw new UserError('The selected section does not belong to the selected class.');
        }
        $classChanged = (int) $e['class_id'] !== $classId;
        if ($classChanged && Db::val('SELECT 1 FROM marks WHERE enrollment_id = ? LIMIT 1', [$enrollmentId])) {
            throw new UserError('Marks have already been entered for this student in this year, so the class cannot be changed. Move the section only, or contact the administrator.');
        }
        Db::tx(function () use ($e, $enrollmentId, $classId, $sectionId, $rollNo, $classChanged) {
            try {
                Db::exec('UPDATE enrollments SET class_id = ?, section_id = ?, roll_no = ?, updated_at = NOW() WHERE id = ?',
                    [$classId, $sectionId, $rollNo ?: null, $enrollmentId]);
            } catch (\PDOException $ex) {
                if (Db::isDuplicate($ex)) {
                    throw new UserError('That roll number is already used in this section.');
                }
                throw $ex;
            }
            Audit::log('enrollment.change', 'enrollment', $enrollmentId, ['from_class' => (int) $e['class_id'], 'to_class' => $classId, 'from_section' => (int) $e['section_id'], 'to_section' => $sectionId]);
            if ($classChanged) {
                FeeService::resyncAssessment((int) $e['student_id'], (int) $e['academic_year_id'], $classId);
            }
        });
    }

    public static function setStatus(int $enrollmentId, string $status): void
    {
        Auth::require('students.manage');
        if (!in_array($status, ['active', 'left', 'completed'], true)) {
            throw new UserError('Invalid status.');
        }
        Db::exec('UPDATE enrollments SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $enrollmentId]);
        Audit::log('enrollment.status', 'enrollment', $enrollmentId, ['status' => $status]);
    }

    // ---------------- promotion ----------------

    /** The class after $classId by sort order, or null for the last class (Class 10). */
    public static function nextClass(int $classId): ?array
    {
        $cur = AcademicService::classRow($classId);
        if (!$cur) {
            return null;
        }
        return Db::row('SELECT * FROM classes WHERE sort_order > ? ORDER BY sort_order LIMIT 1', [$cur['sort_order']]);
    }

    /**
     * Build the preview. Each row has a suggested action; nothing is changed.
     * Actions: promote | repeat | complete (last class only) | skip.
     */
    public static function promotionPreview(int $sourceYearId, int $targetYearId, ?int $classId = null): array
    {
        Auth::require('promotion.manage');
        $src = AcademicService::year($sourceYearId);
        $tgt = AcademicService::year($targetYearId);
        if (!$src || !$tgt) {
            throw new UserError('Choose both academic years.');
        }
        if ($sourceYearId === $targetYearId) {
            throw new UserError('The source and target years must be different.');
        }
        if ($tgt['start_date'] <= $src['start_date']) {
            throw new UserError('The target year must start after the source year.');
        }
        $sql = 'SELECT e.id AS enrollment_id, e.student_id, e.class_id, e.section_id, e.roll_no, s.full_name, s.admission_no, s.is_active,
                       c.name AS class_name, c.sort_order, sec.name AS section_name,
                       (SELECT t.id FROM enrollments t WHERE t.student_id = e.student_id AND t.academic_year_id = ?) AS target_enrollment_id
                  FROM enrollments e JOIN students s ON s.id = e.student_id JOIN classes c ON c.id = e.class_id JOIN sections sec ON sec.id = e.section_id
                 WHERE e.academic_year_id = ? AND e.status = \'active\' AND s.is_active = 1';
        $p = [$targetYearId, $sourceYearId];
        if ($classId) {
            $sql .= ' AND e.class_id = ?';
            $p[] = $classId;
        }
        $rows = Db::all($sql . ' ORDER BY c.sort_order, sec.name, s.full_name', $p);
        $sectionsByClass = [];
        foreach (AcademicService::sections(null, true) as $s) {
            $sectionsByClass[(int) $s['class_id']][] = $s;
        }
        $out = [];
        foreach ($rows as $r) {
            $next = self::nextClass((int) $r['class_id']);
            $r['next_class_id'] = $next ? (int) $next['id'] : null;
            $r['next_class_name'] = $next['name'] ?? null;
            $r['flag'] = null;
            $r['action'] = 'promote';
            $target = $next ? (int) $next['id'] : (int) $r['class_id'];
            $secs = $sectionsByClass[$target] ?? [];
            $same = null;
            foreach ($secs as $s) {
                if ($s['name'] === $r['section_name']) {
                    $same = $s;
                }
            }
            $r['target_section_id'] = $same ? (int) $same['id'] : ($secs ? (int) $secs[0]['id'] : null);
            if ($r['target_enrollment_id']) {
                $r['action'] = 'skip';
                $r['flag'] = 'Already enrolled in the target year.';
            } elseif (!$next) {
                $r['action'] = 'complete';
                $r['flag'] = 'Last class (Class 10): no next class in this system. Mark as completed, or choose Repeat.';
            } elseif ($r['target_section_id'] === null) {
                $r['action'] = 'skip';
                $r['flag'] = 'Next class has no active section. Add a section first.';
            }
            $out[] = $r;
        }
        return $out;
    }

    /**
     * Execute chosen actions. $items: list of [enrollment_id, action, section_id]. Each student is its own
     * transaction so one bad row never blocks the others. Returns per-student outcomes.
     */
    public static function promote(int $sourceYearId, int $targetYearId, array $items): array
    {
        Auth::require('promotion.manage');
        $results = [];
        foreach ($items as $it) {
            $eid = (int) ($it['enrollment_id'] ?? 0);
            $action = (string) ($it['action'] ?? 'skip');
            $secId = (int) ($it['section_id'] ?? 0);
            $row = Db::row('SELECT e.*, s.full_name FROM enrollments e JOIN students s ON s.id = e.student_id WHERE e.id = ? AND e.academic_year_id = ?', [$eid, $sourceYearId]);
            if (!$row) {
                $results[] = ['name' => '#' . $eid, 'ok' => false, 'msg' => 'Enrollment not found in the source year.'];
                continue;
            }
            $name = $row['full_name'];
            if ($action === 'skip') {
                $results[] = ['name' => $name, 'ok' => true, 'msg' => 'Skipped (unchanged).'];
                continue;
            }
            try {
                Db::tx(function () use ($row, $action, $secId, $targetYearId, &$results, $name) {
                    if ($row['status'] !== 'active') {
                        throw new UserError('This enrollment is no longer active.');
                    }
                    if ($action === 'complete') {
                        if (self::nextClass((int) $row['class_id'])) {
                            throw new UserError('Only students of the last class can be marked completed.');
                        }
                        Db::exec("UPDATE enrollments SET status = 'completed', updated_at = NOW() WHERE id = ?", [$row['id']]);
                        Audit::log('promotion.complete', 'enrollment', $row['id'], ['student_id' => (int) $row['student_id']]);
                        $results[] = ['name' => $name, 'ok' => true, 'msg' => 'Marked as completed (finished Class 10).'];
                        return;
                    }
                    if (!in_array($action, ['promote', 'repeat'], true)) {
                        throw new UserError('Unknown action.');
                    }
                    $targetClass = (int) $row['class_id'];
                    if ($action === 'promote') {
                        $next = self::nextClass($targetClass);
                        if (!$next) {
                            throw new UserError('There is no next class. Choose Repeat or Completed.');
                        }
                        $targetClass = (int) $next['id'];
                    }
                    if ($action === 'repeat') {
                        $secId = (int) $row['section_id'];   // a repeating student stays in the same section
                    }
                    if (!$secId) {
                        throw new UserError('Choose a section.');
                    }
                    self::enroll((int) $row['student_id'], $targetYearId, $targetClass, $secId, null, (int) $row['id']);
                    Db::exec('UPDATE enrollments SET status = ?, updated_at = NOW() WHERE id = ?', [$action === 'promote' ? 'promoted' : 'repeated', $row['id']]);
                    Audit::log('promotion.' . $action, 'enrollment', $row['id'], ['student_id' => (int) $row['student_id'], 'target_year' => $targetYearId, 'target_class' => $targetClass]);
                    $results[] = ['name' => $name, 'ok' => true, 'msg' => $action === 'promote' ? 'Promoted.' : 'Kept in the same class (repeat).'];
                });
            } catch (UserError $e) {
                $results[] = ['name' => $name, 'ok' => false, 'msg' => $e->getMessage()];
            }
        }
        return $results;
    }
}
