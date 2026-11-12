<?php
/**
 * Application bootstrap: constants, autoloader, error handling, session, security headers.
 * Included by every public entry point.
 */

define('APP_VERSION', '1.0.0');
define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', APP_ROOT . '/app');
define('STORAGE_DIR', APP_ROOT . '/storage');
define('TEMPLATE_DIR', APP_ROOT . '/templates');
define('MODULE_DIR', APP_ROOT . '/modules');
define('DB_DIR', APP_ROOT . '/database');

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('AlphaOmega School Management needs PHP 8.0 or newer. Detected: ' . PHP_VERSION);
}

spl_autoload_register(function (string $class): void {
    if (strpos($class, 'App\\') !== 0) {
        return;
    }
    $path = APP_DIR . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require_once APP_DIR . '/Core/helpers.php';

use App\Core\Config;

Config::load();
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');

// ---- Error handling: log everything, show nothing sensitive ----
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_DIR . '/logs/php-error.log');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e): void {
    $ref = substr(bin2hex(random_bytes(4)), 0, 8);
    $msg = sprintf("[%s] ref=%s %s: %s in %s:%d\n%s\n", date('c'), $ref, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    @file_put_contents(STORAGE_DIR . '/logs/app-errors.log', $msg, FILE_APPEND | LOCK_EX);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $debug = (bool) Config::get('app.debug', false);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>';
    echo '<body style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:10vh auto;padding:0 16px">';
    echo '<h2>Something went wrong</h2><p>The request could not be completed. Nothing was saved unless you saw a success message.</p>';
    echo '<p>Error reference: <b>' . htmlspecialchars($ref) . '</b> (see <code>storage/logs/app-errors.log</code>).</p>';
    if ($debug) {
        echo '<pre style="white-space:pre-wrap">' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    }
    echo '<p><a href="javascript:history.back()">Go back</a></p></body>';
});

set_error_handler(function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

// ---- Security headers ----
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    header('Cache-Control: no-store');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

// ---- Session ----
if (PHP_SAPI !== 'cli') {
    App\Core\Session::start();
}
