<?php
use App\Core\View;
use App\Services\UserService;

View::page('users/list', ['users' => UserService::all()], 'Users');
