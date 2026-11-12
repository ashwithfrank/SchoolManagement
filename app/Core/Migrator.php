<?php
namespace App\Core;

use PDO;

/** Applies database/schema.sql (version 1) and numbered files in database/migrations/. */
class Migrator
{
    public const BASELINE = 1;

    /** Split a .sql file into statements. Comment lines (-- ...) are removed first. */
    public static function statements(string $sql): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $sql) as $line) {
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }
            $lines[] = $line;
        }
        $parts = preg_split('/;\s*(?:\R|$)/', implode("\n", $lines));
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    public static function currentVersion(PDO $pdo): int
    {
        try {
            $v = $pdo->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();
            return $v === null || $v === false ? 0 : (int) $v;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** @return array<int,string> version => file path for files above $from */
    public static function files(int $from): array
    {
        $out = [];
        foreach (glob(DB_DIR . '/migrations/*.sql') ?: [] as $f) {
            if (preg_match('/^(\d{3,})_/', basename($f), $m) && (int) $m[1] > max($from, self::BASELINE)) {
                $out[(int) $m[1]] = $f;
            }
        }
        ksort($out);
        return $out;
    }

    public static function latestVersion(): int
    {
        $f = self::files(self::BASELINE);
        return $f ? max(array_keys($f)) : self::BASELINE;
    }

    /** Create the schema on an empty database, or apply pending migrations. Returns versions applied. */
    public static function run(PDO $pdo): array
    {
        $applied = [];
        $cur = self::currentVersion($pdo);
        if ($cur === 0) {
            $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
            if ($exists > 0) {
                throw new UserError('The selected database is not empty. Choose an empty database (or a new name) for a fresh installation.');
            }
            foreach (self::statements((string) file_get_contents(DB_DIR . '/schema.sql')) as $st) {
                $pdo->exec($st);
            }
            $pdo->prepare('INSERT INTO schema_migrations (version, name, applied_at) VALUES (?, ?, NOW())')
                ->execute([self::BASELINE, 'baseline']);
            $applied[] = self::BASELINE;
            $cur = self::BASELINE;
        }
        foreach (self::files($cur) as $ver => $file) {
            foreach (self::statements((string) file_get_contents($file)) as $st) {
                $pdo->exec($st);
            }
            $pdo->prepare('INSERT INTO schema_migrations (version, name, applied_at) VALUES (?, ?, NOW())')
                ->execute([$ver, basename($file)]);
            $applied[] = $ver;
        }
        return $applied;
    }

    /** Reference data + default role permissions (fresh install only). */
    public static function seed(PDO $pdo): void
    {
        foreach (self::statements((string) file_get_contents(DB_DIR . '/seed.sql')) as $st) {
            $pdo->exec($st);
        }
        $roleIds = [];
        foreach ($pdo->query('SELECT id, code FROM roles') as $r) {
            $roleIds[$r['code']] = (int) $r['id'];
        }
        $ins = $pdo->prepare('INSERT INTO role_permissions (role_id, permission) VALUES (?, ?)');
        foreach (Permissions::defaults() as $role => $perms) {
            foreach ($perms as $p) {
                $ins->execute([$roleIds[$role], $p]);
            }
        }
    }
}
