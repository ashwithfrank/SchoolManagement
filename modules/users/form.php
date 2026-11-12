<?php
use App\Core\{Flash, Http, Router, UserError, View};
use App\Services\{EmployeeService, UserService};

$id = Http::intParam('id');
$user = $id ? UserService::find($id) : null;
if ($id && !$user) {
    Router::notFound();
}
$d = $user ? ['username' => $user['username'], 'full_name' => $user['full_name'], 'role' => $user['role_code'], 'employee_id' => $user['employee_id']] : ['role' => 'office'];
if (Http::isPost()) {
    $d = $_POST;
    try {
        if ($user) {
            UserService::update($id, $_POST);
            Flash::success('User updated.');
        } else {
            UserService::create($_POST);
            Flash::success('User created.');
        }
        redirect(url('users', 'list'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
unset($d['password'], $d['password2']);
View::page('users/form', ['user' => $user, 'd' => $d, 'employees' => EmployeeService::activeList()], $user ? 'Edit user' : 'Add user');
