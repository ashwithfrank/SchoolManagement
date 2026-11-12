<?php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\{Auth, Csrf, Flash};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url());
}
Csrf::enforce();
Auth::logout();
Flash::success('You have been signed out.');
redirect(base_path() . '/login.php');
