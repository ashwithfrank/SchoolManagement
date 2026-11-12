<?php
namespace App\Services\Backup;

/**
 * Optional off-site copy of an ENCRYPTED backup. Only providers that have been implemented and
 * tested may be listed in BackupService::providers(). Normal school work never needs the internet.
 */
interface CloudProvider
{
    public function id(): string;

    public function label(): string;

    /** True when the administrator has configured it (e.g. a folder path). */
    public function isConfigured(): bool;

    /** Copy the file; return a human-readable description of where it went. Throws on failure. */
    public function upload(string $localPath): string;
}
