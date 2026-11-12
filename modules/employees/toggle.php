<?php
use App\Core\{Flash, Http};
use App\Services\EmployeeService;

$id = Http::intParam('id');
if (Http::isPost() && $id) {
    EmployeeService::setActive($id, Http::intParam('active', 0) === 1);
    Flash::success('Employee status updated.');
}
redirect(url('employees', 'view', ['id' => $id]));
