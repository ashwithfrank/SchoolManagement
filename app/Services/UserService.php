<?php
namespace App\Services;

use App\Core\{Audit, Auth, Db, Permissions, UserError, Validator};

class UserService
{
    public static function all(): array
    {
        Auth::require('users.manage');
        return Db::all('SELECT u.*, r.name AS role_name, r.code AS role_code, e.full_name AS employee_name, e.employee_code
                          FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN employees e ON e.id = u.employee_id ORDER BY u.is_active DESC, u.username');
    }

    public static function find(int $id): ?array
    {
        return Db::row('SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]);
    }

    public static function roles(): array
    {
        return Db::all('SELECT * FROM roles ORDER BY id');
    }

    private static function roleId(string $code): int
    {
        $id = Db::val('SELECT id FROM roles WHERE code = ?', [$code]);
        if (!$id) {
            throw new UserError('Unknown role.');
        }
        return (int) $id;
    }

    private static function activeAdmins(?int $excludeId = null): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' AND u.is_active = 1 AND u.id <> ?", [$excludeId ?? 0]);
    }

    public static function create(array $in): int
    {
        Auth::require('users.manage');
        $v = new Validator($in);
        $username = $v->text('username', 'Username', true, 40);
        if ($username !== null && !preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) {
            $v->error('username', 'Username must be 3-40 characters: letters, numbers, dot, dash or underscore.');
        }
        $name = $v->text('full_name', 'Full name', true, 120);
        $role = $v->in('role', 'Role', array_keys(Permissions::ROLES));
        $emp = $v->int('employee_id', 'Employee', false, 1);
        $v->check();
        $pw = (string) ($in['password'] ?? '');
        if ($pw !== (string) ($in['password2'] ?? '')) {
            throw new UserError('The two passwords do not match.');
        }
        if ($msg = Auth::passwordProblem($pw, (string) $username)) {
            throw new UserError($msg);
        }
        if ($emp && !EmployeeService::find($emp)) {
            throw new UserError('Employee not found.');
        }
        try {
            $id = Db::insert('INSERT INTO users (username, password_hash, full_name, role_id, employee_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())',
                [$username, Auth::hash($pw), $name, self::roleId($role), $emp, ]);
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                throw new UserError('That username, or that employee, already has an account.');
            }
            throw $e;
        }
        Audit::log('user.create', 'user', $id, ['username' => $username, 'role' => $role]);
        return $id;
    }

    public static function update(int $id, array $in): void
    {
        Auth::require('users.manage');
        $u = self::find($id);
        if (!$u) {
            throw new UserError('User not found.');
        }
        $v = new Validator($in);
        $name = $v->text('full_name', 'Full name', true, 120);
        $role = $v->in('role', 'Role', array_keys(Permissions::ROLES));
        $emp = $v->int('employee_id', 'Employee', false, 1);
        $v->check();
        if ($u['role_code'] === 'admin' && $role !== 'admin' && self::activeAdmins($id) === 0) {
            throw new UserError('At least one active Admin must remain.');
        }
        if ($id === Auth::id() && $role !== 'admin') {
            throw new UserError('You cannot remove your own Admin role.');
        }
        if ($emp && !EmployeeService::find($emp)) {
            throw new UserError('Employee not found.');
        }
        try {
            Db::exec('UPDATE users SET full_name = ?, role_id = ?, employee_id = ?, updated_at = NOW() WHERE id = ?', [$name, self::roleId($role), $emp, $id]);
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                throw new UserError('That employee already has an account.');
            }
            throw $e;
        }
        Audit::log('user.update', 'user', $id, ['role' => $role]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Auth::require('users.manage');
        $u = self::find($id);
        if (!$u) {
            throw new UserError('User not found.');
        }
        if (!$active) {
            if ($id === Auth::id()) {
                throw new UserError('You cannot deactivate your own account.');
            }
            if ($u['role_code'] === 'admin' && self::activeAdmins($id) === 0) {
                throw new UserError('At least one active Admin must remain.');
            }
        }
        Db::exec('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?', [$active ? 1 : 0, $id]);
        Audit::log($active ? 'user.activate' : 'user.deactivate', 'user', $id);
    }

    public static function resetPassword(int $id, string $pw, string $pw2): void
    {
        Auth::require('users.manage');
        $u = self::find($id);
        if (!$u) {
            throw new UserError('User not found.');
        }
        if ($pw !== $pw2) {
            throw new UserError('The two passwords do not match.');
        }
        if ($msg = Auth::passwordProblem($pw, $u['username'])) {
            throw new UserError($msg);
        }
        Db::exec('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?', [Auth::hash($pw), $id]);
        Audit::log('user.password_reset', 'user', $id);
    }

    /** Any signed-in user may change their own password. */
    public static function changeOwnPassword(string $current, string $new, string $new2): void
    {
        $u = Auth::user();
        if (!$u) {
            throw new UserError('Please sign in.');
        }
        if (!password_verify($current, $u['password_hash'])) {
            Audit::log('password.change_failed', 'user', (int) $u['id']);
            throw new UserError('Your current password is not correct.');
        }
        if ($new !== $new2) {
            throw new UserError('The two new passwords do not match.');
        }
        if ($msg = Auth::passwordProblem($new, $u['username'])) {
            throw new UserError($msg);
        }
        Db::exec('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?', [Auth::hash($new), $u['id']]);
        Audit::log('password.change', 'user', (int) $u['id']);
        \App\Core\Session::regenerate();
    }

    /** First-run: create the initial administrator (only when no user exists). */
    public static function createFirstAdmin(string $username, string $fullName, string $pw): int
    {
        if ((int) Db::val('SELECT COUNT(*) FROM users') > 0) {
            throw new UserError('Setup has already been completed.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) {
            throw new UserError('Username must be 3-40 characters: letters, numbers, dot, dash or underscore.');
        }
        if (trim($fullName) === '') {
            throw new UserError('Enter the administrator\'s name.');
        }
        if ($msg = Auth::passwordProblem($pw, $username)) {
            throw new UserError($msg);
        }
        $id = Db::insert('INSERT INTO users (username, password_hash, full_name, role_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
            [$username, Auth::hash($pw), trim($fullName), self::roleId('admin')]);
        Audit::log('setup.admin_created', 'user', $id, ['username' => $username], $id, $username);
        return $id;
    }
}
