<?php
namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function valid(?string $given): bool
    {
        $t = $_SESSION['_csrf'] ?? '';
        return is_string($given) && $t !== '' && hash_equals($t, $given);
    }

    /** Aborts the request (HTTP 419) when a POST lacks a valid token. */
    public static function enforce(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $given = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::valid($given)) {
            http_response_code(419);
            echo '<!doctype html><meta charset="utf-8"><title>Session expired</title>';
            echo '<body style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:12vh auto;padding:0 16px">';
            echo '<h2>This form has expired</h2><p>For your safety the request was rejected. Go back, reload the page and try again.</p>';
            echo '<p><a href="' . e(url()) . '">Return to the dashboard</a></p></body>';
            exit;
        }
    }
}
