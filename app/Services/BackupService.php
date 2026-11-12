<?php
namespace App\Services;

use App\Core\{Audit, Auth, BackupCrypto, Db, Migrator, Settings, UserError};
use App\Services\Backup\{CloudProvider, FolderSyncProvider};
use PDO;
use ZipArchive;

/**
 * Versioned backup archives: database.sql (consistent snapshot) + photos/ + manifest.json,
 * optionally encrypted. Restore validates first, takes a safety backup, then swaps DB and photos together.
 */
class BackupService
{
    public const FORMAT = 'AOSMS-BACKUP';
    public const FORMAT_VERSION = 1;
    private const MARK = "\n-- @@AOSMS-STMT@@\n";

    /** @return CloudProvider[] only providers that are really implemented */
    public static function providers(): array
    {
        return [new FolderSyncProvider()];
    }

    public static function requirements(): array
    {
        $dir = self::backupDir(false);
        return [
            'zip'      => class_exists(ZipArchive::class),
            'openssl'  => function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true),
            'writable' => $dir !== null && is_dir($dir) && is_writable($dir),
            'dir'      => $dir,
        ];
    }

    // ---------- locations ----------

    public static function backupDir(bool $create = true): ?string
    {
        $cfg = trim(Settings::get('backup_dir', ''));
        $dir = $cfg !== '' ? $cfg : STORAGE_DIR . '/backups';
        if ($create && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $real = realpath($dir);
        return $real !== false ? $real : null;
    }

    /** Validate an administrator-supplied destination. Returns an error message or null. */
    public static function dirProblem(string $dir): ?string
    {
        $dir = trim($dir);
        if ($dir === '') {
            return null;
        }
        if (!is_dir($dir)) {
            return 'That folder does not exist. Create it first.';
        }
        $real = realpath($dir);
        $pub = realpath(APP_ROOT . '/public');
        if ($real === false || ($pub && strpos($real . DIRECTORY_SEPARATOR, $pub . DIRECTORY_SEPARATOR) === 0)) {
            return 'Backups must not be stored inside the public web folder.';
        }
        if (!is_writable($real)) {
            return 'That folder is not writable by the web server.';
        }
        return null;
    }

    private static function tmpDir(): string
    {
        $d = STORAGE_DIR . '/tmp';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    private static function limits(): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
    }

    // ---------- SQL dump / load ----------

    /** Stream a consistent SQL dump to $path. Returns table => row count. */
    public static function dumpTo(string $path): array
    {
        $pdo = Db::pdo();
        $fh = fopen($path, 'wb');
        if (!$fh) {
            throw new UserError('Cannot write the temporary backup file.');
        }
        $counts = [];
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        try {
            fwrite($fh, "-- AlphaOmega School Management backup, created " . date('c') . self::MARK);
            fwrite($fh, 'SET FOREIGN_KEY_CHECKS=0' . self::MARK);
            fwrite($fh, "SET NAMES utf8mb4" . self::MARK);
            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
            foreach ($tables as $t) {
                $name = $t[0];
                $q = '`' . str_replace('`', '``', $name) . '`';
                $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
                fwrite($fh, "DROP TABLE IF EXISTS $q" . self::MARK . $create . self::MARK);
                $counts[$name] = 0;
                if ($name === 'login_attempts') {
                    continue;
                }
                $st = $pdo->query("SELECT * FROM $q");
                $cols = null;
                $batch = [];
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $cols = $cols ?? '(' . implode(',', array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row))) . ')';
                    $vals = [];
                    foreach ($row as $v) {
                        $vals[] = $v === null ? 'NULL' : (is_int($v) ? (string) $v : $pdo->quote((string) $v));
                    }
                    $batch[] = '(' . implode(',', $vals) . ')';
                    $counts[$name]++;
                    if (count($batch) >= 200) {
                        fwrite($fh, "INSERT INTO $q $cols VALUES " . implode(',', $batch) . self::MARK);
                        $batch = [];
                    }
                }
                if ($batch) {
                    fwrite($fh, "INSERT INTO $q $cols VALUES " . implode(',', $batch) . self::MARK);
                }
            }
            fwrite($fh, 'SET FOREIGN_KEY_CHECKS=1' . self::MARK);
        } finally {
            $pdo->exec('ROLLBACK');
            fclose($fh);
        }
        return $counts;
    }

    /** Drop every table, then run the dump. The caller is responsible for a safety backup. */
    private static function loadDump(string $sqlPath): void
    {
        $pdo = Db::pdo();
        $sql = (string) file_get_contents($sqlPath);
        $stmts = array_filter(array_map('trim', explode(self::MARK, $sql)), fn ($s) => $s !== '');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t[0]) . '`');
        }
        foreach ($stmts as $st) {
            if (strncmp($st, '--', 2) === 0 && strpos($st, "\n") === false) {
                continue;
            }
            $pdo->exec($st);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    // ---------- create ----------

    /**
     * @return array{id:int, path:string, file:string, size:int, cloud:?string}
     */
    public static function create(bool $includePhotos, ?string $password, string $kind = 'manual', ?string $note = null, bool $copyToCloud = false): array
    {
        Auth::require('backups.manage');
        self::limits();
        $req = self::requirements();
        if (!$req['zip']) {
            throw new UserError('The PHP "zip" extension is not enabled. In XAMPP, enable extension=zip in php.ini and restart Apache.');
        }
        if ($password !== null && $password !== '' && !$req['openssl']) {
            throw new UserError('Encryption needs the PHP openssl extension with AES-256-GCM.');
        }
        $dir = self::backupDir();
        if (!$dir || !is_writable($dir)) {
            throw new UserError('The backup folder is missing or not writable. Check Settings.');
        }
        $encrypt = $password !== null && $password !== '';
        if ($copyToCloud && !$encrypt) {
            throw new UserError('Only encrypted backups may be copied to a synced/cloud folder. Enter an encryption password.');
        }
        $tmp = self::tmpDir();
        $stamp = date('Ymd-His');
        $base = "aosms-backup-$stamp" . ($kind === 'safety' ? '-safety' : '');
        $sqlPath = "$tmp/$base.sql";
        $zipPath = "$tmp/$base.zip";
        try {
            $counts = self::dumpTo($sqlPath);
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new UserError('Cannot create the backup archive.');
            }
            $photoCount = 0;
            if ($includePhotos) {
                foreach (glob(PhotoService::dir() . '/*') ?: [] as $f) {
                    if (is_file($f) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', basename($f))) {
                        $zip->addFile($f, 'photos/' . basename($f));
                        $photoCount++;
                    }
                }
            }
            $zip->addFile($sqlPath, 'database.sql');
            $manifest = [
                'format' => self::FORMAT, 'format_version' => self::FORMAT_VERSION, 'app_version' => APP_VERSION,
                'schema_version' => Migrator::currentVersion(Db::pdo()), 'created_at' => date('c'),
                'includes_photos' => $includePhotos, 'photo_count' => $photoCount, 'tables' => $counts,
                'sql_sha256' => hash_file('sha256', $sqlPath),
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            $zip->close();

            $final = $encrypt ? "$dir/$base.aosbak" : "$dir/$base.zip";
            if ($encrypt) {
                file_put_contents($final, BackupCrypto::encrypt((string) file_get_contents($zipPath), (string) $password));
            } elseif (!@copy($zipPath, $final)) {
                throw new UserError('Could not write the backup file to the backup folder.');
            }
            @chmod($final, 0640);
        } finally {
            @unlink($sqlPath);
            @unlink($zipPath);
        }
        $size = (int) filesize($final);
        $sha = hash_file('sha256', $final);
        $id = Db::insert('INSERT INTO backups (filename, directory, kind, is_encrypted, includes_photos, size_bytes, sha256, schema_version, note, created_by, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [basename($final), $dir, $kind, $encrypt ? 1 : 0, $includePhotos ? 1 : 0, $size, $sha, $manifest['schema_version'], $note ? mb_substr($note, 0, 200) : null, Auth::id()]);
        Audit::log('backup.create', 'backup', $id, ['file' => basename($final), 'kind' => $kind, 'encrypted' => $encrypt, 'photos' => $photoCount]);
        $cloud = null;
        if ($copyToCloud) {
            foreach (self::providers() as $p) {
                if ($p->isConfigured()) {
                    $cloud = $p->upload($final);
                    Audit::log('backup.cloud_copy', 'backup', $id, ['provider' => $p->id()]);
                }
            }
            if ($cloud === null) {
                throw new UserError('The backup was created, but no synced folder is configured in Settings, so it was not copied.');
            }
        }
        return ['id' => $id, 'path' => $final, 'file' => basename($final), 'size' => $size, 'cloud' => $cloud];
    }

    // ---------- list ----------

    public static function all(): array
    {
        Auth::require('backups.manage');
        $rows = Db::all('SELECT b.*, u.full_name AS created_by_name FROM backups b LEFT JOIN users u ON u.id = b.created_by ORDER BY b.id DESC LIMIT 200');
        foreach ($rows as &$r) {
            $r['exists'] = is_file(rtrim($r['directory'], '/\\') . DIRECTORY_SEPARATOR . $r['filename']);
        }
        return $rows;
    }

    public static function find(int $id): ?array
    {
        return Db::row('SELECT * FROM backups WHERE id = ?', [$id]);
    }

    public static function filePath(array $b): string
    {
        $name = basename($b['filename']);
        if ($name !== $b['filename'] || !preg_match('/^[A-Za-z0-9._-]+\.(zip|aosbak)$/', $name)) {
            throw new UserError('Invalid backup file name.');
        }
        return rtrim($b['directory'], '/\\') . DIRECTORY_SEPARATOR . $name;
    }

    /** Register backup files found in the folder but not in the list (e.g. after a restore replaced the table). */
    public static function rescan(): int
    {
        Auth::require('backups.manage');
        $dir = self::backupDir();
        $added = 0;
        foreach (glob($dir . '/aosms-backup-*.{zip,aosbak}', GLOB_BRACE) ?: [] as $f) {
            $name = basename($f);
            if (Db::val('SELECT 1 FROM backups WHERE filename = ? AND directory = ?', [$name, $dir])) {
                continue;
            }
            $enc = substr($name, -7) === '.aosbak';
            Db::exec("INSERT INTO backups (filename, directory, kind, is_encrypted, includes_photos, size_bytes, sha256, schema_version, note, created_by, created_at)
                      VALUES (?, ?, ?, ?, 1, ?, ?, 0, 'Found in folder', ?, FROM_UNIXTIME(?))",
                [$name, $dir, strpos($name, '-safety') !== false ? 'safety' : 'imported', $enc ? 1 : 0, filesize($f), hash_file('sha256', $f), Auth::id(), filemtime($f)]);
            $added++;
        }
        return $added;
    }

    public static function import(array $upload): int
    {
        Auth::require('backups.manage');
        self::limits();
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new UserError('The upload failed or no file was chosen. Large backups may exceed PHP upload_max_filesize; copy the file into the backup folder and use "Rescan folder" instead.');
        }
        $head = (string) file_get_contents($upload['tmp_name'], false, null, 0, 8);
        $enc = BackupCrypto::isEncrypted($head);
        if (!$enc && strncmp($head, "PK", 2) !== 0) {
            throw new UserError('That file is not an AlphaOmega backup.');
        }
        $dir = self::backupDir();
        $name = 'aosms-backup-imported-' . date('Ymd-His') . ($enc ? '.aosbak' : '.zip');
        if (!move_uploaded_file($upload['tmp_name'], "$dir/$name")) {
            throw new UserError('Could not store the uploaded backup.');
        }
        $id = Db::insert("INSERT INTO backups (filename, directory, kind, is_encrypted, includes_photos, size_bytes, sha256, schema_version, note, created_by, created_at)
                          VALUES (?, ?, 'imported', ?, 1, ?, ?, 0, ?, ?, NOW())", [$name, $dir, $enc ? 1 : 0, filesize("$dir/$name"), hash_file('sha256', "$dir/$name"), mb_substr('Uploaded: ' . basename((string) ($upload['name'] ?? '')), 0, 200), Auth::id()]);
        Audit::log('backup.import', 'backup', $id, ['file' => $name]);
        return $id;
    }

    // ---------- validate / restore ----------

    /**
     * Open (decrypting if needed) and validate a backup. Returns ['zipPath','manifest','tmp'] where zipPath is a plain zip in a temp dir.
     * The caller must call cleanup($tmp).
     */
    public static function open(array $b, ?string $password): array
    {
        self::limits();
        if (!class_exists(ZipArchive::class)) {
            throw new UserError('The PHP "zip" extension is required.');
        }
        $path = self::filePath($b);
        if (!is_file($path)) {
            throw new UserError('The backup file is missing from the backup folder.');
        }
        if (!hash_equals((string) $b['sha256'], (string) hash_file('sha256', $path))) {
            throw new UserError('The backup file has changed since it was recorded (checksum mismatch). Do not restore it.');
        }
        $tmp = self::tmpDir() . '/restore-' . bin2hex(random_bytes(6));
        mkdir($tmp, 0775, true);
        $zipPath = $path;
        $head = (string) file_get_contents($path, false, null, 0, 8);
        if (BackupCrypto::isEncrypted($head)) {
            if ($password === null || $password === '') {
                self::cleanup($tmp);
                throw new UserError('Enter the backup password.');
            }
            try {
                $plain = BackupCrypto::decrypt((string) file_get_contents($path), $password);
            } catch (UserError $e) {
                self::cleanup($tmp);
                throw $e;
            }
            $zipPath = "$tmp/backup.zip";
            file_put_contents($zipPath, $plain);
            unset($plain);
        }
        try {
            $manifest = self::validateZip($zipPath);
        } catch (\Throwable $e) {
            self::cleanup($tmp);
            throw $e;
        }
        return ['zipPath' => $zipPath, 'manifest' => $manifest, 'tmp' => $tmp];
    }

    /** @return array manifest */
    public static function validateZip(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new UserError('The backup archive cannot be opened; it may be damaged.');
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== self::FORMAT) {
            $zip->close();
            throw new UserError('This is not an AlphaOmega backup (manifest missing or wrong).');
        }
        if ((int) ($manifest['format_version'] ?? 0) > self::FORMAT_VERSION) {
            $zip->close();
            throw new UserError('This backup was made by a newer version of the application.');
        }
        if ((int) ($manifest['schema_version'] ?? 0) > Migrator::latestVersion()) {
            $zip->close();
            throw new UserError('This backup has a newer database layout than this application understands. Update the application first.');
        }
        $sql = $zip->getFromName('database.sql');
        if ($sql === false || !hash_equals((string) ($manifest['sql_sha256'] ?? ''), hash('sha256', $sql))) {
            $zip->close();
            throw new UserError('The database inside the backup is missing or fails its integrity check.');
        }
        foreach (['users', 'students', 'payments', 'roles', 'schema_migrations'] as $t) {
            if (strpos($sql, 'CREATE TABLE `' . $t . '`') === false) {
                $zip->close();
                throw new UserError("The backup does not contain the expected table '$t'.");
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (!in_array($n, ['manifest.json', 'database.sql'], true) && !preg_match('#^photos/[a-f0-9]{32}\.(jpg|png|webp)$#', $n)) {
                $zip->close();
                throw new UserError('The backup contains an unexpected file and was rejected: ' . substr($n, 0, 60));
            }
        }
        $zip->close();
        return $manifest;
    }

    public static function cleanup(string $tmp): void
    {
        if (!is_dir($tmp) || strpos(realpath($tmp) ?: '', realpath(self::tmpDir())) !== 0) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmp);
    }

    /** Validate only (nothing is changed). Returns manifest. */
    public static function verify(int $id, ?string $password): array
    {
        Auth::require('backups.manage');
        $b = self::find($id) ?: throw new UserError('Backup not found.');
        $o = self::open($b, $password);
        self::cleanup($o['tmp']);
        Audit::log('backup.verify', 'backup', $id);
        return $o['manifest'];
    }

    /**
     * Restore: validate -> safety backup -> replace database and photos -> migrate. On failure the safety
     * backup is re-applied. Returns a summary. The caller should sign the user out afterwards.
     */
    public static function restore(int $id, ?string $password): array
    {
        Auth::require('backups.manage');
        $b = self::find($id) ?: throw new UserError('Backup not found.');
        $o = self::open($b, $password);                     // 1. validate
        $who = Auth::user();
        $safety = null;
        try {
            $safety = self::create(true, null, 'safety', 'Automatic safety copy before restore of ' . $b['filename']); // 2. safety backup
            $zip = new ZipArchive();
            $zip->open($o['zipPath']);
            $sqlPath = $o['tmp'] . '/database.sql';
            file_put_contents($sqlPath, $zip->getFromName('database.sql'));
            $photoTmp = $o['tmp'] . '/photos';
            mkdir($photoTmp, 0775, true);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (strncmp($n, 'photos/', 7) === 0) {
                    file_put_contents($photoTmp . '/' . basename($n), $zip->getFromIndex($i));
                }
            }
            $zip->close();
            try {
                self::loadDump($sqlPath);                    // 4. database
                Migrator::run(Db::pdo());
            } catch (\Throwable $e) {
                self::rollbackToSafety($safety);
                throw new UserError('The restore failed and the previous data was put back. Reason: ' . $e->getMessage());
            }
            $photoDir = PhotoService::dir();                 //    photos, together with the database
            $old = $photoDir . '.before-restore-' . date('YmdHis');
            $swapped = false;
            if (is_dir($photoDir)) {
                $swapped = @rename($photoDir, $old);
                if (!$swapped) {
                    self::rollbackToSafety($safety);
                    throw new UserError('The photo folder could not be replaced, so the restore was cancelled and the previous data was put back.');
                }
            }
            if (!@rename($photoTmp, $photoDir)) {
                if ($swapped) {
                    @rename($old, $photoDir);
                }
                self::rollbackToSafety($safety);
                throw new UserError('The photos could not be restored, so the restore was cancelled and the previous data was put back.');
            }
            if ($swapped) {
                self::cleanup2($old);
            }
        } finally {
            self::cleanup($o['tmp']);
        }
        // Tables were replaced, so the backup list and audit log now reflect the backup's moment in time.
        $dir = self::backupDir();
        foreach (glob($dir . '/aosms-backup-*.{zip,aosbak}', GLOB_BRACE) ?: [] as $f) {
            $name = basename($f);
            if (!Db::val('SELECT 1 FROM backups WHERE filename = ?', [$name])) {
                Db::exec("INSERT INTO backups (filename, directory, kind, is_encrypted, includes_photos, size_bytes, sha256, schema_version, note, created_at)
                          VALUES (?, ?, ?, ?, 1, ?, ?, 0, 'Re-registered after restore', FROM_UNIXTIME(?))",
                    [$name, $dir, strpos($name, '-safety') !== false ? 'safety' : 'imported', substr($name, -7) === '.aosbak' ? 1 : 0, filesize($f), hash_file('sha256', $f), filemtime($f)]);
            }
        }
        Audit::log('backup.restore', 'backup', null, ['restored_file' => $b['filename'], 'by' => $who['username'] ?? '', 'safety_copy' => $safety['file'] ?? null], null, $who['username'] ?? null);
        return ['manifest' => $o['manifest'], 'safety' => $safety['file'] ?? null];
    }

    private static function cleanup2(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    private static function rollbackToSafety(?array $safety): void
    {
        if (!$safety) {
            return;
        }
        $tmp = self::tmpDir() . '/rollback-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0775, true);
        try {
            $zip = new ZipArchive();
            if ($zip->open($safety['path']) === true) {
                file_put_contents("$tmp/database.sql", $zip->getFromName('database.sql'));
                $zip->close();
                self::loadDump("$tmp/database.sql");
            }
        } finally {
            self::cleanup($tmp);
        }
    }
}
