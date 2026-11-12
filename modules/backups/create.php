<?php
use App\Core\{Flash, Http, UserError};
use App\Services\BackupService;

if (Http::isPost()) {
    try {
        $pw = (string) ($_POST['password'] ?? '');
        if ($pw !== '' && $pw !== (string) ($_POST['password2'] ?? '')) {
            throw new UserError('The two encryption passwords do not match.');
        }
        if ($pw !== '' && mb_strlen($pw) < 8) {
            throw new UserError('Use an encryption password of at least 8 characters.');
        }
        $r = BackupService::create(!empty($_POST['photos']), $pw === '' ? null : $pw, 'manual', Http::strParam('note'), !empty($_POST['cloud']));
        Flash::success('Backup created: ' . $r['file'] . ' (' . number_format($r['size'] / 1024, 1) . ' KB).' . ($r['cloud'] ? ' Copy placed in the synced folder.' : ''));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
redirect(url('backups', 'index'));
