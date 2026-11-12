<?php
namespace App\Core;

/** Module registry, sidebar menu and the guarded dispatcher used by public/index.php. */
class Router
{
    /** module => [label, icon, perm needed to enter, actions => [action => permission|null (module permission)]] */
    public static function modules(): array
    {
        return [
            'dashboard' => ['perm' => 'dashboard.view', 'actions' => ['index' => null]],
            'account'   => ['perm' => null, 'actions' => ['password' => null, 'year' => null]],
            'students'  => ['perm' => 'students.view', 'actions' => ['list' => null, 'view' => null, 'form' => null, 'photo' => null, 'enrol' => null, 'toggle' => null, 'lookup' => 'fees.view']],
            'academics' => ['perm' => 'academics.view', 'actions' => ['years' => null, 'classes' => null, 'subjects' => null, 'coordinators' => null, 'promotion' => 'promotion.manage']],
            'employees' => ['perm' => 'employees.view', 'actions' => ['list' => null, 'form' => null, 'view' => null, 'toggle' => null]],
            'transport' => ['perm' => 'transport.view', 'actions' => ['buses' => null, 'bus_form' => 'buses.manage', 'assignments' => null, 'assign' => 'transport.assign', 'assignment' => 'transport.assign']],
            'fees'      => ['perm' => 'fees.view', 'actions' => ['dues' => null, 'statement' => null, 'structure' => 'fees.structure']],
            'payments'  => ['perm' => 'fees.view', 'actions' => ['list' => null, 'record' => 'payments.record', 'receipt' => null, 'reverse' => 'payments.reverse']],
            'exams'     => ['perm' => 'exams.view', 'actions' => ['list' => null, 'papers' => null, 'marks' => 'marks.enter', 'results' => null]],
            'reports'   => ['perm' => null, 'actions' => ['index' => null, 'run' => null]],
            'users'     => ['perm' => 'users.manage', 'actions' => ['list' => null, 'form' => null, 'password' => null, 'toggle' => null]],
            'backups'   => ['perm' => 'backups.manage', 'actions' => ['index' => null, 'create' => null, 'restore' => null, 'verify' => null, 'download' => null, 'import' => null, 'rescan' => null]],
            'settings'  => ['perm' => 'settings.manage', 'actions' => ['index' => null, 'upgrade' => null]],
            'audit'     => ['perm' => 'audit.view', 'actions' => ['index' => null]],
        ];
    }

    /** Sidebar: [group => [[module, action, label, icon, permission(s)]]] */
    public static function menu(): array
    {
        $m = [
            'Overview' => [['dashboard', 'index', 'Dashboard', 'fa-gauge-high', 'dashboard.view']],
            'People' => [
                ['students', 'list', 'Students', 'fa-user-graduate', 'students.view'],
                ['employees', 'list', 'Employees', 'fa-id-badge', 'employees.view'],
            ],
            'Academics' => [
                ['academics', 'years', 'Years & promotion', 'fa-calendar-days', 'academics.view'],
                ['academics', 'classes', 'Classes & sections', 'fa-school', 'academics.view'],
                ['academics', 'subjects', 'Subjects', 'fa-book', 'academics.view'],
                ['exams', 'list', 'Exams & marks', 'fa-pen-to-square', 'exams.view'],
            ],
            'Transport' => [
                ['transport', 'buses', 'Buses', 'fa-bus', 'transport.view'],
                ['transport', 'assignments', 'Student transport', 'fa-route', 'transport.view'],
            ],
            'Fees' => [
                ['fees', 'dues', 'Fees & dues', 'fa-indian-rupee-sign', 'fees.view'],
                ['payments', 'list', 'Payments', 'fa-receipt', 'fees.view'],
                ['fees', 'structure', 'Fee structure', 'fa-table-list', 'fees.structure'],
            ],
            'Reports' => [['reports', 'index', 'Reports', 'fa-chart-column', ['reports.students', 'reports.finance', 'reports.academic', 'reports.staff']]],
            'Administration' => [
                ['users', 'list', 'Users', 'fa-users-gear', 'users.manage'],
                ['backups', 'index', 'Backup & restore', 'fa-database', 'backups.manage'],
                ['settings', 'index', 'Settings', 'fa-gear', 'settings.manage'],
                ['audit', 'index', 'Audit log', 'fa-clipboard-list', 'audit.view'],
            ],
        ];
        $out = [];
        foreach ($m as $group => $items) {
            foreach ($items as $it) {
                $perms = (array) $it[4];
                foreach ($perms as $p) {
                    if (Auth::can($p)) {
                        $out[$group][] = $it;
                        break;
                    }
                }
            }
        }
        return $out;
    }

    /** Which sidebar entry should be highlighted for the page being viewed. */
    public static function isActive(string $menuMod, string $menuAct, string $curMod, string $curAct): bool
    {
        if ($menuMod !== $curMod) {
            return false;
        }
        $alias = [
            'transport' => ['assign' => 'assignments', 'assignment' => 'assignments', 'bus_form' => 'buses'],
            'academics' => ['promotion' => 'years', 'coordinators' => 'classes'],
            'fees'      => ['statement' => 'dues'],
        ];
        $single = ['students' => 'list', 'employees' => 'list', 'payments' => 'list', 'exams' => 'list', 'users' => 'list', 'reports' => 'index', 'dashboard' => 'index'];
        if (isset($single[$curMod])) {
            return true;
        }
        $norm = $alias[$curMod][$curAct] ?? $curAct;
        return $menuAct === $norm;
    }

    public static function dispatch(): void
    {
        $mod = preg_replace('/[^a-z_]/', '', (string) ($_GET['m'] ?? 'dashboard')) ?: 'dashboard';
        $act = preg_replace('/[^a-z_]/', '', (string) ($_GET['a'] ?? 'index')) ?: 'index';
        $mods = self::modules();
        if (!isset($mods[$mod])) {
            self::notFound();
        }
        $def = $mods[$mod];
        if ($act === 'index' && !array_key_exists('index', $def['actions'])) {
            $act = (string) array_key_first($def['actions']);
        }
        if (!array_key_exists($act, $def['actions'])) {
            self::notFound();
        }
        if (!Auth::check()) {
            Flash::info('Please sign in to continue.');
            redirect(base_path() . '/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
        }
        Csrf::enforce();
        try {
            if ($def['perm']) {
                Auth::require($def['perm']);
            }
            if ($def['actions'][$act]) {
                Auth::require($def['actions'][$act]);
            }
            $file = MODULE_DIR . "/$mod/$act.php";
            if (!is_file($file)) {
                self::notFound();
            }
            (static function (string $__file): void {
                require $__file;
            })($file);
        } catch (Forbidden $e) {
            http_response_code(403);
            View::page('errors/403', ['message' => $e->getMessage()], 'Access denied');
        }
    }

    public static function notFound(): void
    {
        http_response_code(404);
        if (Auth::check()) {
            View::page('errors/404', [], 'Page not found');
        } else {
            echo 'Not found';
        }
        exit;
    }
}
