<?php
use App\Core\{Flash, Http, Marks, UserError, View};
use App\Services\{AcademicService, ExamService};

$examId = (int) Http::intParam('exam', 0);
$exam = ExamService::exam($examId);
if (!$exam) {
    Flash::error('Exam not found.');
    redirect(url('exams', 'list'));
}
$classId = Http::intParam('class');
$sectionId = Http::intParam('section');
$data = null;
if ($classId) {
    try {
        $data = ExamService::classResults($examId, $classId, $sectionId);
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
if ($data && Http::strParam('export') === 'csv') {
    $head = ['Section', 'Roll', 'Admission no', 'Student'];
    foreach ($data['papers'] as $p) {
        $head[] = $p['subject_name'] . ' (/' . Marks::display($p['max_marks']) . ')';
    }
    array_push($head, 'Total', 'Max', 'Percentage', 'Result');
    $rows = [];
    foreach ($data['students'] as $s) {
        $r = [$s['section_name'], $s['roll_no'], $s['admission_no'], $s['full_name']];
        foreach ($data['papers'] as $p) {
            $c = $s['cells'][$p['id']];
            $r[] = $c['eval']['state'] === 'absent' ? 'AB' : ($c['eval']['state'] === 'missing' ? '' : Marks::display($c['scored']));
        }
        array_push($r, $s['overall']['total'], $s['overall']['max'], $s['overall']['percentage'] ?? '', $s['overall']['result']);
        $rows[] = $r;
    }
    \App\Core\Audit::log('export.exam_results', 'exam', $examId, ['class_id' => $classId]);
    \App\Core\Http::csv('results-' . preg_replace('/[^A-Za-z0-9]+/', '-', $exam['name']) . '.csv', $head, $rows);
    exit;
}
View::page('exams/results', ['exam' => $exam, 'data' => $data, 'classes' => AcademicService::classes(), 'sections' => AcademicService::sections(), 'classId' => $classId, 'sectionId' => $sectionId], 'Results');
