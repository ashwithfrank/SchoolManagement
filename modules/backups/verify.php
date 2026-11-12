<?php
use App\Core\{Flash, Http, UserError};
use App\Services\BackupService;

if (Http::isPost()) {
    try {
        $m = BackupService::verify((int) Http::intParam('id', 0), (string) ($_POST['password'] ?? '') ?: null);
        Flash::success('Backup is valid. Created ' . ($m['created_at'] ?? '?') . ', schema version ' . ($m['schema_version'] ?? '?') . ', ' . (int) ($m['photo_count'] ?? 0) . ' photo(s). Nothing was changed.');
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
redirect(url('backups', 'index'));
