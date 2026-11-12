<?php
use App\Core\{Flash, Http, UserError, View};
use App\Services\UserService;

if (Http::isPost()) {
    try {
        UserService::changeOwnPassword((string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''), (string) ($_POST['new2'] ?? ''));
        Flash::success('Your password has been changed.');
        redirect(url());
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('account/password', [], 'Change password');
