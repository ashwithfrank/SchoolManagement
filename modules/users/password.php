<?php
use App\Core\{Flash, Http, Router, UserError, View};
use App\Services\UserService;

$id = (int) Http::intParam('id', 0);
$user = UserService::find($id);
if (!$user) {
    Router::notFound();
}
if (Http::isPost()) {
    try {
        UserService::resetPassword($id, (string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
        Flash::success('Password reset for ' . $user['username'] . '.');
        redirect(url('users', 'list'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('users/password', ['user' => $user], 'Reset password');
