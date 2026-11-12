<?php
use App\Core\{Flash, Http, UserError, View};
use App\Services\{AcademicService, ExamService};

$year = AcademicService::currentYear();
if (!$year) {
    Flash::warning('Create an academic year first.');
    redirect(url('academics', 'years'));
}
$yid = (int) $year['id'];
if (Http::isPost()) {
    try {
        $id = Http::intParam('id');
        ExamService::saveExam($id, $yid, Http::strParam('name'), Http::strParam('exam_date'));
        Flash::success($id ? 'Exam updated.' : 'Exam created.');
        redirect(url('exams', 'list'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
$edit = Http::intParam('edit');
$editRow = $edit ? ExamService::exam($edit) : null;
if ($editRow && (int) $editRow['academic_year_id'] !== $yid) {
    $editRow = null;
}
View::page('exams/list', ['year' => $year, 'exams' => ExamService::exams($yid), 'editRow' => $editRow], 'Exams');
