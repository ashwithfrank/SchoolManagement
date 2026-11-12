<?php
use App\Core\{Flash, Http, UserError};
use App\Services\BackupService;

if (Http::isPost()) {
    try {
        BackupService::import($_FILES['backup'] ?? []);
        Flash::success('Backup file added to the list. Use Verify before restoring.');
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
redirect(url('backups', 'index'));
