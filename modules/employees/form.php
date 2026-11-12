<?php
use App\Core\{Auth, Flash, Http, Router, UserError, View};
use App\Services\EmployeeService;

Auth::require('employees.manage');
$id = Http::intParam('id');
$emp = $id ? EmployeeService::find($id) : null;
if ($id && !$emp) {
    Router::notFound();
}
$d = $emp ?? [];
if (Http::isPost()) {
    $d = $_POST;
    try {
        $newId = EmployeeService::save($id, $_POST);
        Flash::success('Employee saved.');
        redirect(url('employees', 'view', ['id' => $newId]));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('employees/form', ['emp' => $emp, 'd' => $d], $emp ? 'Edit employee' : 'Add employee');
