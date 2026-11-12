<?php
use App\Core\{Flash, Http, UserError, View};
use App\Services\{AcademicService, ExamService};

$examId = (int) Http::intParam('exam', 0);
$exam = ExamService::exam($examId);
if (!$exam) {
    Flash::error('Exam not found.');
    redirect(url('exams', 'list'));
}
if (Http::isPost() && can('exams.papers')) {
    try {
        ExamService::savePaper($examId, (int) Http::intParam('class_id', 0), (int) Http::intParam('subject_id', 0), Http::strParam('max_marks'), Http::strParam('min_marks'));
        Flash::success('Paper saved.');
        redirect(url('exams', 'papers', ['exam' => $examId]));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('exams/papers', ['exam' => $exam, 'papers' => ExamService::papers($examId), 'classes' => AcademicService::classes(), 'subjects' => AcademicService::subjects(true)], 'Exam papers');
