<?php
use App\Core\{Flash, Http, UserError};
use App\Services\{EnrollmentService, AcademicService, StudentService};

$sid = Http::intParam('student_id');
if (!Http::isPost() || !$sid) {
    redirect(url('students', 'list'));
}
try {
    $year = (int) Http::intParam('year_id', 0);
    $class = (int) Http::intParam('class_id', 0);
    $section = (int) Http::intParam('section_id', 0);
    $roll = Http::intParam('roll_no');
    if (!$class || !$section) {
        throw new UserError('Choose a class and a section.');
    }
    if (Http::strParam('op') === 'change') {
        $en = StudentService::enrollmentFor($sid, $year);
        if (!$en) {
            throw new UserError('The student is not enrolled in that year.');
        }
        EnrollmentService::change((int) $en['id'], $class, $section, $roll);
        Flash::success('Class and section updated.');
    } else {
        \App\Core\Auth::require('students.manage');
        EnrollmentService::enroll($sid, $year, $class, $section, $roll);
        Flash::success('Student enrolled.');
    }
} catch (UserError $e) {
    Flash::error($e->getMessage());
}
redirect(url('students', 'view', ['id' => $sid, 'tab' => 'enrollment']));
