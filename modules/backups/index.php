<?php
use App\Core\{Settings, View};
use App\Services\BackupService;

View::page('backups/index', ['backups' => BackupService::all(), 'req' => BackupService::requirements(), 'cloud' => BackupService::providers(), 'cloudDir' => Settings::get('cloud_sync_dir')], 'Backup & restore');
