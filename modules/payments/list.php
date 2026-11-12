<?php
use App\Core\{Dates, Http, View};
use App\Services\{AcademicService, FeeService};

$year = AcademicService::currentYear();
$from = Http::strParam('from');
$to = Http::strParam('to');
$f = [
    'year_id' => $year ? (int) $year['id'] : null,
    'from' => Dates::isValid($from) ? $from : null, 'to' => Dates::isValid($to) ? $to : null,
    'class_id' => Http::intParam('class'), 'section_id' => Http::intParam('section'),
    'category' => isset(FeeService::CATEGORIES[Http::strParam('category')]) ? Http::strParam('category') : null,
    'method' => isset(FeeService::METHODS[Http::strParam('method')]) ? Http::strParam('method') : null,
    'status' => in_array(Http::strParam('status'), ['valid', 'reversed'], true) ? Http::strParam('status') : null,
    'q' => Http::strParam('q'),
];
$pager = null;
$rows = FeeService::searchPayments($f, $pager);
View::page('payments/list', ['year' => $year, 'rows' => $rows, 'pager' => $pager, 'totals' => FeeService::paymentTotals($f), 'f' => $f,
    'classes' => AcademicService::classes(), 'sections' => AcademicService::sections()], 'Payments');
