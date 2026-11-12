<?php
use App\Core\{Auth, Http, Router, View};
use App\Services\{AcademicService, ExamService, FeeService, StudentService, TransportService};

$id = Http::intParam('id');
$s = $id ? StudentService::find($id) : null;
if (!$s || !StudentService::canView($id)) {
    Router::notFound();
}
$year = AcademicService::currentYear();
$tab = Http::strParam('tab', 'profile');
$tabs = ['profile' => 'Profile', 'enrollment' => 'Admission & classes'];
if (Auth::can('fees.view')) { $tabs['fees'] = 'Fees'; $tabs['payments'] = 'Payments'; }
if (Auth::can('transport.view')) { $tabs['transport'] = 'Transport'; }
if (Auth::can('exams.view')) { $tabs['results'] = 'Academic results'; }
if (!isset($tabs[$tab])) { $tab = 'profile'; }

$d = ['s' => $s, 'year' => $year, 'tab' => $tab, 'tabs' => $tabs,
    'enrollments' => StudentService::enrollments($id),
    'current' => $year ? StudentService::enrollmentFor($id, (int) $year['id']) : null,
    'classes' => AcademicService::classes(), 'sections' => AcademicService::sections()];
if ($tab === 'fees' && $year) {
    $d['summary'] = FeeService::summary($id, (int) $year['id']);
    $d['adjustments'] = FeeService::adjustments($id, (int) $year['id']);
}
if ($tab === 'payments') { $d['payments'] = FeeService::studentPayments($id); }
if ($tab === 'transport') { $d['transport'] = TransportService::studentHistory($id); }
if ($tab === 'results') { $d['results'] = ExamService::studentResults($id); }
View::page('students/view', $d, $s['full_name']);
