<?php
namespace App\Core;

class Auth
{
    private static ?array $user = null;
    private static ?array $perms = null;
    private const MAX_FAILS = 5;
    private const WINDOW_MIN = 15;

    // ---------- current user ----------

    /** Loads the signed-in user fresh from the database, enforcing idle/absolute timeouts. */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid) {
            return null;
        }
        $now = time();
        $idle = ((int) Config::get('app.idle_minutes', 30)) * 60;
        $abs = ((int) Config::get('app.max_session_hrs', 12)) * 3600;
        $ua = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (($now - ($_SESSION['last'] ?? 0)) > $idle
            || ($now - ($_SESSION['login_at'] ?? 0)) > $abs
            || !hash_equals($_SESSION['ua'] ?? '', $ua)) {
            Session::destroy();
            Session::start();
            Flash::warning('You were signed out for security. Please sign in again.');
            return null;
        }
        $u = Db::row('SELECT u.*, r.code AS role_code, r.name AS role_name
                        FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [(int) $uid]);
        if (!$u || !$u['is_active']) {
            Session::destroy();
            Session::start();
            return null;
        }
        $_SESSION['last'] = $now;
        return self::$user = $u;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u ? $u['role_code'] : null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    // ---------- permissions ----------

    public static function can(string $perm): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ($u['role_code'] === 'admin') {
            return isset(Permissions::ALL[$perm]);
        }
        if (self::$perms === null) {
            $rows = Db::all('SELECT permission FROM role_permissions WHERE role_id = ?', [(int) $u['role_id']]);
            self::$perms = array_column($rows, 'permission');
        }
        return in_array($perm, self::$perms, true);
    }

    /** Server-side gate used by every protected action. */
    public static function require(string $perm): void
    {
        if (!self::check()) {
            throw new Forbidden('Please sign in.');
        }
        if (!self::can($perm)) {
            Audit::log('access.denied', 'permission', null, ['permission' => $perm, 'uri' => $_SERVER['REQUEST_URI'] ?? '']);
            throw new Forbidden('You do not have permission to do that.');
        }
    }

    public static function requireAny(array $perms): void
    {
        foreach ($perms as $p) {
            if (self::can($p)) {
                return;
            }
        }
        self::require($perms[0]);
    }

    // ---------- sign in / out ----------

    public static function attempt(string $username, string $password): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $username = trim($username);
        if (self::throttled($username, $ip)) {
            throw new UserError('Too many failed sign-in attempts. Please wait ' . self::WINDOW_MIN . ' minutes and try again.');
        }
        $u = Db::row('SELECT * FROM users WHERE username = ?', [$username]);
        // Always run a verification so timing does not reveal whether the username exists.
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok = password_verify($password, $hash);
        if (!$u || !$ok || !$u['is_active']) {
            Db::exec('INSERT INTO login_attempts (username, ip, attempted_at) VALUES (?, ?, NOW())', [mb_substr($username, 0, 60), mb_substr($ip, 0, 45)]);
            Audit::log('login.failed', 'user', $u['id'] ?? null, ['username' => mb_substr($username, 0, 60)], $u['id'] ?? null, $username);
            return false;
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Db::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        Db::exec('DELETE FROM login_attempts WHERE username = ?', [mb_substr($username, 0, 60)]);
        Session::regenerate();
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['login_at'] = time();
        $_SESSION['last'] = time();
        $_SESSION['ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        unset($_SESSION['_csrf']);
        Db::exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
        self::$user = null;
        self::$perms = null;
        Audit::log('login.success', 'user', (int) $u['id'], [], (int) $u['id'], $u['username']);
        return true;
    }

    private static function throttled(string $username, string $ip): bool
    {
        Db::exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        $byUser = (int) Db::val('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > (NOW() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE)', [mb_substr($username, 0, 60)]);
        $byIp = (int) Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE)', [mb_substr($ip, 0, 45)]);
        return $byUser >= self::MAX_FAILS || $byIp >= self::MAX_FAILS * 4;
    }

    public static function logout(): void
    {
        if ($u = self::user()) {
            Audit::log('logout', 'user', (int) $u['id']);
        }
        Session::destroy();
        self::$user = null;
        self::$perms = null;
    }

    // ---------- passwords ----------

    /** Returns an error message, or null when the password is acceptable. */
    public static function passwordProblem(string $password, string $username = ''): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Password must contain at least one letter and one number.';
        }
        if ($username !== '' && strcasecmp($password, $username) === 0) {
            return 'Password must not be the same as the username.';
        }
        return null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
