<?php
use App\Core\Http;
use App\Services\AcademicService;

if (Http::isPost()) {
    AcademicService::selectYear((int) Http::intParam('year_id', 0));
}
$ret = (string) ($_POST['return'] ?? '');
$base = base_path() . '/';
redirect(strncmp($ret, $base, strlen($base)) === 0 && !preg_match('/[\r\n]/', $ret) ? $ret : url());
