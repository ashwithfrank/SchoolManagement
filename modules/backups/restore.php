<?php
use App\Core\{Auth, Flash, Http, Router, Session, UserError, View};
use App\Services\BackupService;

$id = (int) Http::intParam('id', 0);
$b = BackupService::find($id);
if (!$b) {
    Router::notFound();
}
if (Http::isPost()) {
    try {
        if (trim(Http::strParam('confirm')) !== 'RESTORE') {
            throw new UserError('Type RESTORE (capital letters) to confirm.');
        }
        $r = BackupService::restore($id, (string) ($_POST['password'] ?? '') ?: null);
        Auth::logout();
        Session::start();
        Flash::success('Restore complete. Database and photos were replaced together. A safety copy of the previous data was saved as ' . ($r['safety'] ?? '(none)') . '. Please sign in again.');
        redirect(base_path() . '/login.php');
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('backups/restore', ['b' => $b], 'Restore backup');
