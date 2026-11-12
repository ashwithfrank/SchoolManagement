<?php
use App\Core\{Dates, Flash, Http, UserError, View};
use App\Services\{AcademicService, StudentService, TransportService};

$year = AcademicService::currentYear();
if (!$year) {
    Flash::warning('Create an academic year first.');
    redirect(url('academics', 'years'));
}
$d = ['start_date' => Dates::today(), 'student_id' => Http::intParam('student')];
if (Http::isPost()) {
    $d = $_POST;
    try {
        $_POST['year_id'] = (string) $year['id'];
        TransportService::assign($_POST);
        Flash::success('Student assigned to the bus.');
        redirect(url('transport', 'assignments'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
$sel = !empty($d['student_id']) ? StudentService::find((int) $d['student_id']) : null;
View::page('transport/assign', ['year' => $year, 'd' => $d, 'selected' => $sel, 'buses' => TransportService::buses(true)], 'Assign bus');
