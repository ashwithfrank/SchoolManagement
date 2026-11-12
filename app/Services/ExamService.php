<?php
namespace App\Services;

use App\Core\{Audit, Auth, Db, Marks, Money, UserError};

class ExamService
{
    public static function exams(int $yearId): array
    {
        Auth::require('exams.view');
        return Db::all('SELECT x.*, (SELECT COUNT(*) FROM exam_papers p WHERE p.exam_id = x.id) AS papers FROM exams x WHERE x.academic_year_id = ? ORDER BY COALESCE(x.exam_date, x.created_at) DESC, x.id DESC', [$yearId]);
    }

    public static function exam(int $id): ?array
    {
        return Db::row('SELECT x.*, y.name AS year_name FROM exams x JOIN academic_years y ON y.id = x.academic_year_id WHERE x.id = ?', [$id]);
    }

    public static function saveExam(?int $id, int $yearId, string $name, ?string $date): int
    {
        Auth::require('exams.manage');
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new UserError('Enter an exam name such as "Term 1" or "Unit Test 2".');
        }
        if ($date !== null && $date !== '' && !\App\Core\Dates::isValid($date)) {
            throw new UserError('Enter a valid exam date.');
        }
        $date = $date === '' ? null : $date;
        if (!AcademicService::year($yearId)) {
            throw new UserError('Academic year not found.');
        }
        try {
            if ($id) {
                Db::exec('UPDATE exams SET name = ?, exam_date = ? WHERE id = ?', [$name, $date, $id]);
                Audit::log('exam.update', 'exam', $id, ['name' => $name]);
                return $id;
            }
            $id = Db::insert('INSERT INTO exams (academic_year_id, name, exam_date, created_by, created_at) VALUES (?, ?, ?, ?, NOW())', [$yearId, $name, $date, Auth::id()]);
            Audit::log('exam.create', 'exam', $id, ['name' => $name, 'year_id' => $yearId]);
            return $id;
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                throw new UserError('An exam with that name already exists in this academic year.');
            }
            throw $e;
        }
    }

    // ---------- papers (subject + class + max/min marks) ----------

    public static function papers(int $examId, ?int $classId = null): array
    {
        Auth::require('exams.view');
        $sql = 'SELECT p.*, c.name AS class_name, s.name AS subject_name,
                       (SELECT COUNT(*) FROM marks m WHERE m.exam_paper_id = p.id) AS marks_entered
                  FROM exam_papers p JOIN classes c ON c.id = p.class_id JOIN subjects s ON s.id = p.subject_id WHERE p.exam_id = ?';
        $p = [$examId];
        if ($classId) {
            $sql .= ' AND p.class_id = ?';
            $p[] = $classId;
        }
        return Db::all($sql . ' ORDER BY c.sort_order, s.name', $p);
    }

    public static function paper(int $id): ?array
    {
        return Db::row('SELECT p.*, x.academic_year_id, x.name AS exam_name, c.name AS class_name, s.name AS subject_name
                          FROM exam_papers p JOIN exams x ON x.id = p.exam_id JOIN classes c ON c.id = p.class_id JOIN subjects s ON s.id = p.subject_id WHERE p.id = ?', [$id]);
    }

    public static function savePaper(int $examId, int $classId, int $subjectId, ?string $max, ?string $min): int
    {
        Auth::require('exams.papers');
        $exam = self::exam($examId);
        if (!$exam) {
            throw new UserError('Exam not found.');
        }
        $mx = Money::parseDecimal($max);
        $mn = Money::parseDecimal($min);
        $mxh = Marks::hundredths($mx);
        $mnh = Marks::hundredths($mn);
        if ($mx === null || $mxh <= 0) {
            throw new UserError('Maximum marks must be a number greater than zero (up to 2 decimals).');
        }
        if ($mn === null || $mnh > $mxh) {
            throw new UserError('Minimum (passing) marks must be a number from 0 up to the maximum marks.');
        }
        $allowed = Scope::subjectIdsForClass((int) $exam['academic_year_id'], $classId);
        if ($allowed !== null && !in_array($subjectId, $allowed, true)) {
            throw new UserError('You can only set marks for subjects you are assigned to teach in this class.');
        }
        if (!AcademicService::classRow($classId) || !Db::val('SELECT 1 FROM subjects WHERE id = ? AND is_active = 1', [$subjectId])) {
            throw new UserError('Choose a valid class and an active subject.');
        }
        return Db::tx(function () use ($examId, $classId, $subjectId, $mx, $mn, $mxh) {
            $existing = Db::row('SELECT * FROM exam_papers WHERE exam_id = ? AND class_id = ? AND subject_id = ? FOR UPDATE', [$examId, $classId, $subjectId]);
            if ($existing) {
                $over = Db::val('SELECT COUNT(*) FROM marks WHERE exam_paper_id = ? AND scored_marks > ?', [$existing['id'], $mx]);
                if ($over > 0) {
                    throw new UserError("$over student(s) already have marks above $mx. Correct those marks before lowering the maximum.");
                }
                Db::exec('UPDATE exam_papers SET max_marks = ?, min_marks = ?, updated_by = ?, updated_at = NOW() WHERE id = ?', [$mx, $mn, Auth::id(), $existing['id']]);
                Audit::log('paper.update', 'exam_paper', $existing['id'], ['old_max' => $existing['max_marks'], 'old_min' => $existing['min_marks'], 'max' => $mx, 'min' => $mn]);
                return (int) $existing['id'];
            }
            $id = Db::insert('INSERT INTO exam_papers (exam_id, class_id, subject_id, max_marks, min_marks, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$examId, $classId, $subjectId, $mx, $mn, Auth::id()]);
            Audit::log('paper.create', 'exam_paper', $id, ['exam_id' => $examId, 'class_id' => $classId, 'subject_id' => $subjectId, 'max' => $mx, 'min' => $mn]);
            return $id;
        });
    }

    // ---------- marks entry ----------

    /** Students of a section for a paper, with any stored mark. */
    public static function sheet(int $paperId, int $sectionId): array
    {
        Auth::require('marks.enter');
        $paper = self::paper($paperId);
        if (!$paper) {
            throw new UserError('Paper not found.');
        }
        $sec = AcademicService::section($sectionId);
        if (!$sec || (int) $sec['class_id'] !== (int) $paper['class_id']) {
            throw new UserError('That section does not belong to the paper\'s class.');
        }
        if (!Scope::canMark((int) $paper['academic_year_id'], $sectionId, (int) $paper['subject_id'])) {
            throw new UserError('You are not assigned to enter marks for this subject in this section.');
        }
        return Db::all("SELECT e.id AS enrollment_id, e.roll_no, s.full_name, s.admission_no, m.scored_marks, m.is_absent, m.updated_at
                          FROM enrollments e JOIN students s ON s.id = e.student_id
                          LEFT JOIN marks m ON m.enrollment_id = e.id AND m.exam_paper_id = ?
                         WHERE e.academic_year_id = ? AND e.section_id = ? AND e.status <> 'left' AND s.is_active = 1
                         ORDER BY COALESCE(e.roll_no, 99999), s.full_name", [$paperId, $paper['academic_year_id'], $sectionId]);
    }

    /**
     * @param array<int,array{score:string,absent:bool}> $entries keyed by enrollment id
     * Blank rows that have no stored mark stay "not entered" (never saved as zero).
     * @return array{saved:int, unchanged:int}
     */
    public static function saveMarks(int $paperId, int $sectionId, array $entries): array
    {
        Auth::require('marks.enter');
        $paper = self::paper($paperId);
        if (!$paper) {
            throw new UserError('Paper not found.');
        }
        if (!Scope::canMark((int) $paper['academic_year_id'], $sectionId, (int) $paper['subject_id'])) {
            throw new UserError('You are not assigned to enter marks for this subject in this section.');
        }
        $maxH = Marks::hundredths($paper['max_marks']);
        $valid = [];
        foreach (Db::all("SELECT e.id FROM enrollments e WHERE e.academic_year_id = ? AND e.section_id = ? AND e.class_id = ? AND e.status <> 'left'",
            [$paper['academic_year_id'], $sectionId, $paper['class_id']]) as $r) {
            $valid[(int) $r['id']] = true;
        }
        $problems = [];
        $todo = [];
        foreach ($entries as $eid => $en) {
            $eid = (int) $eid;
            if (!isset($valid[$eid])) {
                $problems[] = 'A student in the sheet is not enrolled in this section.';
                continue;
            }
            $absent = !empty($en['absent']);
            $raw = trim((string) ($en['score'] ?? ''));
            if ($absent) {
                $todo[$eid] = [null, 1];
                continue;
            }
            if ($raw === '') {
                continue;
            }
            $dec = Money::parseDecimal($raw);
            if ($dec === null) {
                $problems[] = "'" . mb_substr($raw, 0, 12) . "' is not a valid mark.";
                continue;
            }
            if (Marks::hundredths($dec) > $maxH) {
                $problems[] = "$raw is above the maximum of " . Marks::display($paper['max_marks']) . '.';
                continue;
            }
            $todo[$eid] = [$dec, 0];
        }
        if ($problems) {
            throw new UserError('Nothing was saved. ' . implode(' ', array_slice(array_unique($problems), 0, 5)));
        }
        $saved = 0;
        $same = 0;
        Db::tx(function () use ($paperId, $todo, &$saved, &$same) {
            foreach ($todo as $eid => [$score, $absent]) {
                $old = Db::row('SELECT * FROM marks WHERE exam_paper_id = ? AND enrollment_id = ? FOR UPDATE', [$paperId, $eid]);
                if ($old && (int) $old['is_absent'] === $absent && Marks::hundredths($old['scored_marks']) === Marks::hundredths($score)) {
                    $same++;
                    continue;
                }
                if ($old) {
                    Db::exec('UPDATE marks SET scored_marks = ?, is_absent = ?, updated_by = ?, updated_at = NOW() WHERE id = ?', [$score, $absent, Auth::id(), $old['id']]);
                } else {
                    Db::exec('INSERT INTO marks (exam_paper_id, enrollment_id, scored_marks, is_absent, entered_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
                        [$paperId, $eid, $score, $absent, Auth::id(), Auth::id()]);
                }
                Audit::log('marks.save', 'exam_paper', $paperId, ['enrollment_id' => $eid, 'old' => $old ? ($old['is_absent'] ? 'AB' : $old['scored_marks']) : null, 'new' => $absent ? 'AB' : $score]);
                $saved++;
            }
        });
        return ['saved' => $saved, 'unchanged' => $same];
    }

    // ---------- results ----------

    /** Every exam result line for one student (all years), grouped by exam. */
    public static function studentResults(int $studentId): array
    {
        Auth::require('exams.view');
        $rows = Db::all('SELECT x.id AS exam_id, x.name AS exam_name, x.exam_date, y.name AS year_name, y.start_date,
                                c.name AS class_name, sub.name AS subject_name, p.max_marks, p.min_marks, m.scored_marks, m.is_absent
                           FROM enrollments e
                           JOIN exam_papers p ON p.class_id = e.class_id
                           JOIN exams x ON x.id = p.exam_id AND x.academic_year_id = e.academic_year_id
                           JOIN academic_years y ON y.id = x.academic_year_id
                           JOIN classes c ON c.id = p.class_id JOIN subjects sub ON sub.id = p.subject_id
                           LEFT JOIN marks m ON m.exam_paper_id = p.id AND m.enrollment_id = e.id
                          WHERE e.student_id = ? ORDER BY y.start_date DESC, COALESCE(x.exam_date, x.created_at) DESC, sub.name', [$studentId]);
        $groups = [];
        foreach ($rows as $r) {
            $g = &$groups[$r['exam_id']];
            $g['exam_name'] = $r['exam_name'];
            $g['year_name'] = $r['year_name'];
            $g['class_name'] = $r['class_name'];
            $g['exam_date'] = $r['exam_date'];
            $ev = Marks::evaluate($r['scored_marks'], (bool) $r['is_absent'], $r['max_marks'], $r['min_marks']);
            $g['lines'][] = ['subject' => $r['subject_name'], 'scored' => $r['scored_marks'], 'absent' => (bool) $r['is_absent'], 'max' => $r['max_marks'], 'min' => $r['min_marks'], 'eval' => $ev];
            unset($g);
        }
        foreach ($groups as &$g) {
            $g['overall'] = Marks::overall($g['lines']);
        }
        unset($g);
        return $groups;
    }

    /** Class/section result matrix for one exam. Teachers only see sections they are assigned to. */
    public static function classResults(int $examId, int $classId, ?int $sectionId = null): array
    {
        Auth::require('exams.view');
        $exam = self::exam($examId);
        if (!$exam) {
            throw new UserError('Exam not found.');
        }
        $papers = self::papers($examId, $classId);
        $scope = Scope::sectionIds((int) $exam['academic_year_id']);
        $where = "e.academic_year_id = ? AND e.class_id = ? AND e.status <> 'left'";
        $p = [$exam['academic_year_id'], $classId];
        if ($sectionId) {
            $where .= ' AND e.section_id = ?';
            $p[] = $sectionId;
        }
        if ($scope !== null) {
            [$in, $ip] = Db::in($scope);
            $where .= " AND e.section_id IN ($in)";
            $p = array_merge($p, $ip);
        }
        $students = Db::all("SELECT e.id AS enrollment_id, e.roll_no, s.full_name, s.admission_no, sec.name AS section_name
                               FROM enrollments e JOIN students s ON s.id = e.student_id JOIN sections sec ON sec.id = e.section_id
                              WHERE $where ORDER BY sec.name, COALESCE(e.roll_no, 99999), s.full_name", $p);
        $marks = [];
        if ($papers && $students) {
            [$pin, $pp] = Db::in(array_column($papers, 'id'));
            foreach (Db::all("SELECT exam_paper_id, enrollment_id, scored_marks, is_absent FROM marks WHERE exam_paper_id IN ($pin)", $pp) as $m) {
                $marks[$m['enrollment_id']][$m['exam_paper_id']] = $m;
            }
        }
        foreach ($students as &$s) {
            $lines = [];
            $cells = [];
            foreach ($papers as $pp) {
                $m = $marks[$s['enrollment_id']][$pp['id']] ?? null;
                $line = ['scored' => $m['scored_marks'] ?? null, 'absent' => (bool) ($m['is_absent'] ?? false), 'max' => $pp['max_marks'], 'min' => $pp['min_marks']];
                $lines[] = $line;
                $cells[$pp['id']] = $line + ['eval' => Marks::evaluate($line['scored'], $line['absent'], $line['max'], $line['min'])];
            }
            $s['cells'] = $cells;
            $s['overall'] = Marks::overall($lines);
        }
        unset($s);
        return ['exam' => $exam, 'papers' => $papers, 'students' => $students];
    }
}
