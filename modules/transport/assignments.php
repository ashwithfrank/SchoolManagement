<?php
use App\Core\{Http, View};
use App\Services\{AcademicService, TransportService};

$year = AcademicService::currentYear();
$f = ['bus_id' => Http::intParam('bus'), 'status' => Http::strParam('status', 'active'), 'q' => Http::strParam('q')];
$rows = [];
$pager = null;
if ($year) {
    $rows = TransportService::search((int) $year['id'], $f, $pager);
}
View::page('transport/assignments', ['year' => $year, 'rows' => $rows, 'pager' => $pager, 'f' => $f, 'buses' => TransportService::buses()], 'Student transport');
