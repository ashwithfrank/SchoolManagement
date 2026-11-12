<?php
use App\Core\{Audit, Http, Router};
use App\Services\BackupService;

$b = BackupService::find((int) Http::intParam('id', 0));
if (!$b) {
    Router::notFound();
}
$path = BackupService::filePath($b);
if (!is_file($path)) {
    Router::notFound();
}
Audit::log('backup.download', 'backup', (int) $b['id'], ['file' => $b['filename']]);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $b['filename'] . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
exit;
