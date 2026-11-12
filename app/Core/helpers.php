<?php
/** Small global helpers used by templates and modules. */

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** URL of the public directory (no trailing slash), e.g. /AlphaOmegaSchoolManagement/public */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return rtrim($dir === '/' || $dir === '.' ? '' : $dir, '/');
}

/** Build an in-app URL: url('students','view',['id'=>5]) */
function url(string $module = 'dashboard', string $action = 'index', array $params = []): string
{
    $q = ['m' => $module];
    if ($action !== 'index') {
        $q['a'] = $action;
    }
    return base_path() . '/index.php?' . http_build_query($q + $params);
}

function asset(string $path): string
{
    return base_path() . '/assets/' . ltrim($path, '/');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App\Core\Csrf::token()) . '">';
}

/** One-time token so a double-clicked or re-posted form is recognised as the same submission. */
function idem_field(): string
{
    return '<input type="hidden" name="_idem" value="' . e(bin2hex(random_bytes(16))) . '">';
}

function can(string $permission): bool
{
    return App\Core\Auth::can($permission);
}

function old(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function selected($a, $b): string
{
    return ((string) $a === (string) $b) ? ' selected' : '';
}

function checked($cond): string
{
    return $cond ? ' checked' : '';
}

function money(?int $paise, bool $symbol = true): string
{
    return App\Core\Money::format($paise ?? 0, $symbol);
}

function dmy(?string $date): string
{
    return App\Core\Dates::display($date);
}

function ro_flash(): array
{
    return App\Core\Flash::pull();
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}
