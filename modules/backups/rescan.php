<?php
use App\Core\{Flash, Http, UserError};
use App\Services\BackupService;

if (Http::isPost()) {
    try {
        $n = BackupService::rescan();
        Flash::info($n . ' backup file(s) found in the folder and added to the list.');
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
redirect(url('backups', 'index'));
