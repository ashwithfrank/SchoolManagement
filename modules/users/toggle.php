<?php
use App\Core\{Flash, Http, UserError};
use App\Services\UserService;

if (Http::isPost()) {
    try {
        UserService::setActive((int) Http::intParam('id', 0), Http::intParam('active', 0) === 1);
        Flash::success('User status updated.');
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
redirect(url('users', 'list'));
