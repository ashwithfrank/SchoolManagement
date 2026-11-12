<?php
/** Front controller: every in-app page is reached as index.php?m=<module>&a=<action>. */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\{Auth, Config, Db, Migrator, Router, View};

if (!Config::exists()) {
    redirect(base_path() . '/setup.php');
}
try {
    $pdo = Db::pdo();
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (Throwable $e) {
    @file_put_contents(STORAGE_DIR . '/logs/app-errors.log', '[' . date('c') . '] DB connect/check failed: ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(503);
    echo View::partial('auth/db_error', ['debug' => (bool) Config::get('app.debug', false), 'error' => $e->getMessage()]);
    exit;
}
if ($users === 0) {
    redirect(base_path() . '/setup.php');
}
if (Migrator::currentVersion($pdo) < Migrator::latestVersion()
    && !(($_GET['m'] ?? '') === 'settings' && ($_GET['a'] ?? '') === 'upgrade')) {
    if (Auth::isAdmin()) {
        redirect(url('settings', 'upgrade'));
    }
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Upgrade needed</title><body style="font-family:Segoe UI,Arial;max-width:560px;margin:12vh auto">'
        . '<h2>The system needs an upgrade</h2><p>An administrator must sign in and complete the database upgrade before the system can be used.</p></body>';
    exit;
}
Router::dispatch();
