<?php
use App\Core\{Audit, Db, Flash, Http, Migrator, UserError, View};

$cur = Migrator::currentVersion(Db::pdo());
$latest = Migrator::latestVersion();
if (Http::isPost()) {
    try {
        $applied = Migrator::run(Db::pdo());
        Audit::log('db.upgrade', 'database', null, ['applied' => $applied]);
        Flash::success($applied ? 'Database upgraded (applied: ' . implode(', ', $applied) . ').' : 'Database is already up to date.');
        redirect(url('settings', 'index'));
    } catch (\Throwable $e) {
        Flash::error($e instanceof UserError ? $e->getMessage() : 'Upgrade failed: ' . $e->getMessage() . ' Restore your backup and contact support.');
    }
}
View::page('settings/upgrade', ['cur' => $cur, 'latest' => $latest], 'Database upgrade');
