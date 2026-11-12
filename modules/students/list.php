<?php
use App\Core\{Http, View};
use App\Services\{AcademicService, StudentService};

$year = AcademicService::currentYear();
$f = [
    'q' => Http::strParam('q'), 'class_id' => Http::intParam('class'), 'section_id' => Http::intParam('section'),
    'status' => in_array(Http::strParam('status', 'active'), ['active', 'inactive', 'all'], true) ? Http::strParam('status', 'active') : 'active',
    'enrolled' => in_array(Http::strParam('enrolled', 'all'), ['all', 'yes', 'no'], true) ? Http::strParam('enrolled', 'all') : 'all',
    'sort' => Http::strParam('sort', 'name'), 'dir' => Http::strParam('dir', 'asc') === 'desc' ? 'desc' : 'asc',
];
$rows = [];
$pager = null;
if ($year) {
    $rows = StudentService::search((int) $year['id'], $f, 20, $pager);
}
View::page('students/list', [
    'year' => $year, 'rows' => $rows, 'pager' => $pager, 'f' => $f,
    'classes' => AcademicService::classes(), 'sections' => AcademicService::sections(),
], 'Students');
