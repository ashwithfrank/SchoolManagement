<?php
use App\Core\{Flash, Http, UserError, View};
use App\Services\{AcademicService, ExamService, Scope};

$year = AcademicService::currentYear();
if (!$year) {
    Flash::warning('Create an academic year first.');
    redirect(url('academics', 'years'));
}
$yid = (int) $year['id'];
$examId = Http::intParam('exam');
$classId = Http::intParam('class');
$subjectId = Http::intParam('subject');
$sectionId = Http::intParam('section');

$paper = null;
$sheet = [];
$notice = null;
if ($examId && $classId && $subjectId && $sectionId) {
    foreach (ExamService::papers($examId, $classId) as $p) {
        if ((int) $p['subject_id'] === $subjectId) {
            $paper = $p;
        }
    }
    if (!$paper) {
        $notice = 'No paper exists for that exam, class and subject. Create it under Exams > Papers first.';
    }
}
if ($paper && Http::isPost()) {
    try {
        $entries = [];
        foreach ((array) ($_POST['score'] ?? []) as $eid => $v) {
            $entries[(int) $eid] = ['score' => is_string($v) ? $v : '', 'absent' => !empty($_POST['absent'][$eid])];
        }
        foreach ((array) ($_POST['absent'] ?? []) as $eid => $v) {
            if (!isset($entries[(int) $eid])) {
                $entries[(int) $eid] = ['score' => '', 'absent' => true];
            }
        }
        $r = ExamService::saveMarks((int) $paper['id'], $sectionId, $entries);
        Flash::success($r['saved'] . ' mark(s) saved' . ($r['unchanged'] ? ', ' . $r['unchanged'] . ' unchanged' : '') . '.');
        redirect(url('exams', 'marks', ['exam' => $examId, 'class' => $classId, 'subject' => $subjectId, 'section' => $sectionId]));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
if ($paper) {
    try {
        $sheet = ExamService::sheet((int) $paper['id'], $sectionId);
    } catch (UserError $e) {
        $notice = $e->getMessage();
        $paper = null;
    }
}
View::page('exams/marks', [
    'year' => $year, 'exams' => ExamService::exams($yid), 'classes' => AcademicService::classes(), 'sections' => AcademicService::sections(),
    'subjects' => AcademicService::subjects(true), 'paper' => $paper, 'sheet' => $sheet, 'notice' => $notice,
    'sel' => ['exam' => $examId, 'class' => $classId, 'subject' => $subjectId, 'section' => $sectionId],
], 'Enter marks');
