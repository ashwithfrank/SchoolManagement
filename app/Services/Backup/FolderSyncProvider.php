<?php
namespace App\Services\Backup;

use App\Core\{Settings, UserError};

/**
 * Copies the encrypted backup into a folder that a desktop sync client (OneDrive, Google Drive,
 * Dropbox, a NAS share...) uploads by itself. The application does not talk to any cloud API.
 */
class FolderSyncProvider implements CloudProvider
{
    public function id(): string { return 'folder'; }

    public function label(): string { return 'Synced folder (OneDrive / Google Drive / network share)'; }

    public function dir(): string { return trim(Settings::get('cloud_sync_dir', '')); }

    public function isConfigured(): bool { return $this->dir() !== ''; }

    public function upload(string $localPath): string
    {
        $dir = $this->dir();
        if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
            throw new UserError('The synced folder is not set, does not exist, or is not writable: ' . $dir);
        }
        $dest = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . basename($localPath);
        if (!@copy($localPath, $dest)) {
            throw new UserError('Could not copy the backup into the synced folder.');
        }
        return $dest;
    }
}
