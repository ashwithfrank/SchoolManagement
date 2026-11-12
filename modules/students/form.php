<?php
use App\Core\{Flash, Http, UserError, View, Dates};
use App\Services\{AcademicService, StudentService};

$id = Http::intParam('id');
$student = null;
if ($id) {
    $student = StudentService::find($id);
    if (!$student || !StudentService::canView($id)) {
        App\Core\Router::notFound();
    }
} else {
    App\Core\Auth::require('students.manage');
}
$year = AcademicService::currentYear();
$data = $student ?? ['admission_date' => Dates::today()];
$enrol = ['class_id' => '', 'section_id' => '', 'roll_no' => ''];
if (Http::isPost()) {
    $data = $_POST;
    $enrol = ['class_id' => $_POST['class_id'] ?? '', 'section_id' => $_POST['section_id'] ?? '', 'roll_no' => $_POST['roll_no'] ?? ''];
    try {
        if ($id) {
            StudentService::update($id, $_POST);
            Flash::success('Student details saved.');
            redirect(url('students', 'view', ['id' => $id]));
        }
        $e = null;
        if (Http::intParam('class_id') || Http::intParam('section_id')) {
            $e = ['year_id' => $year ? (int) $year['id'] : 0, 'class_id' => Http::intParam('class_id', 0), 'section_id' => Http::intParam('section_id', 0), 'roll_no' => Http::intParam('roll_no')];
        }
        $newId = StudentService::create($_POST, $e);
        Flash::success('Student admitted. You can now upload a photo.');
        redirect(url('students', 'view', ['id' => $newId]));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('students/form', [
    'student' => $student, 'd' => $data, 'year' => $year, 'enrol' => $enrol,
    'limited' => $id && !can('students.manage'),
    'classes' => AcademicService::classes(), 'sections' => AcademicService::sections(),
], $id ? 'Edit student' : 'Admit student');
