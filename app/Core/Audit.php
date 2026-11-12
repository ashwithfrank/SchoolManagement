<?php
namespace App\Core;

/** Append-only audit trail. The application exposes no update or delete for these rows. */
class Audit
{
    public static function log(string $action, ?string $entityType = null, $entityId = null, array $details = [], ?int $userId = null, ?string $username = null): void
    {
        try {
            if ($userId === null && isset($_SESSION['uid'])) {
                $u = Auth::user();
                $userId = $u ? (int) $u['id'] : null;
                $username = $username ?? ($u['username'] ?? null);
            }
            Db::exec('INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, ip_address, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())', [
                $userId, $username !== null ? mb_substr($username, 0, 60) : null, mb_substr($action, 0, 60),
                $entityType, $entityId !== null ? (string) $entityId : null,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                mb_substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
            ]);
        } catch (\Throwable $e) {
            // Never let logging break the action; record it for the administrator.
            @file_put_contents(STORAGE_DIR . '/logs/app-errors.log', '[' . date('c') . '] audit failure: ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}
