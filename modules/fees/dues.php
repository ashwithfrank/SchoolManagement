<?php
use App\Core\{Http, View};
use App\Services\{AcademicService, FeeService};

$year = AcademicService::currentYear();
$st = Http::strParam('status', 'pending');
$f = ['class_id' => Http::intParam('class'), 'section_id' => Http::intParam('section'), 'q' => Http::strParam('q'),
    'status' => in_array($st, ['pending', 'paid', 'unassessed', 'all'], true) ? $st : 'pending', 'sort' => Http::strParam('sort', 'class')];
$rows = [];
$pager = null;
$totals = null;
if ($year) {
    $rows = FeeService::dues((int) $year['id'], $f, $pager);
    $totals = FeeService::duesTotals((int) $year['id'], $f);
}
View::page('fees/dues', ['year' => $year, 'rows' => $rows, 'pager' => $pager, 'totals' => $totals, 'f' => $f,
    'classes' => AcademicService::classes(), 'sections' => AcademicService::sections()], 'Fees & dues');
