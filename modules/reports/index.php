<?php
use App\Core\{Auth, View};
use App\Services\ReportService;

$groups = [];
foreach (ReportService::definitions() as $key => $d) {
    if (Auth::can($d['perm'])) {
        $groups[$d['perm']][$key] = $d;
    }
}
View::page('reports/index', ['groups' => $groups], 'Reports');
