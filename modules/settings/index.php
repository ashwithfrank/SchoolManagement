<?php
use App\Core\{Audit, Flash, Http, Migrator, Settings, UserError, View};
use App\Services\BackupService;

$d = Settings::all();
if (Http::isPost()) {
    $d = array_merge($d, array_intersect_key($_POST, Settings::DEFAULTS));
    try {
        $in = [];
        $in['school_name'] = trim(Http::strParam('school_name'));
        if ($in['school_name'] === '' || mb_strlen($in['school_name']) > 120) {
            throw new UserError('Enter the school name (up to 120 characters).');
        }
        $in['school_address'] = mb_substr(trim(Http::strParam('school_address')), 0, 255);
        $in['school_phone'] = mb_substr(trim(Http::strParam('school_phone')), 0, 40);
        $in['receipt_prefix'] = strtoupper(trim(Http::strParam('receipt_prefix')));
        if (!preg_match('/^[A-Z0-9-]{1,10}$/', $in['receipt_prefix'])) {
            throw new UserError('Receipt prefix must be 1-10 letters, digits or dashes.');
        }
        $in['receipt_footer'] = mb_substr(trim(Http::strParam('receipt_footer')), 0, 200);
        $in['teachers_all_marks'] = !empty($_POST['teachers_all_marks']) ? '1' : '0';
        $days = Http::intParam('expiry_warning_days');
        if ($days === null || $days < 1 || $days > 365) {
            throw new UserError('Expiry warning days must be between 1 and 365.');
        }
        $in['expiry_warning_days'] = (string) $days;
        $mb = Http::intParam('photo_max_mb');
        if ($mb === null || $mb < 1 || $mb > 10) {
            throw new UserError('Maximum photo size must be between 1 and 10 MB.');
        }
        $in['photo_max_mb'] = (string) $mb;
        foreach (['backup_dir', 'cloud_sync_dir'] as $k) {
            $in[$k] = trim(Http::strParam($k));
            if ($msg = BackupService::dirProblem($in[$k])) {
                throw new UserError(($k === 'backup_dir' ? 'Backup folder: ' : 'Synced folder: ') . $msg);
            }
        }
        $changed = [];
        foreach ($in as $k => $v) {
            if (Settings::get($k) !== $v) {
                $changed[] = $k;
                Settings::set($k, $v);
            }
        }
        Audit::log('settings.update', 'settings', null, ['changed' => $changed]);
        Flash::success('Settings saved.');
        redirect(url('settings', 'index'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('settings/index', ['d' => $d, 'version' => APP_VERSION, 'schema' => Migrator::currentVersion(\App\Core\Db::pdo()), 'latest' => Migrator::latestVersion()], 'Settings');
