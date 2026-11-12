<?php
use App\Core\{Http, View};
use App\Services\EmployeeService;

$q = Http::strParam('q');
$status = in_array(Http::strParam('status', 'active'), ['active', 'inactive', 'all'], true) ? Http::strParam('status', 'active') : 'active';
$pager = null;
$rows = EmployeeService::search($q, $status, $pager);
View::page('employees/list', ['rows' => $rows, 'pager' => $pager, 'q' => $q, 'status' => $status], 'Employees');
