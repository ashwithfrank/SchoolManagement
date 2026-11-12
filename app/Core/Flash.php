<?php
namespace App\Core;

class Flash
{
    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'msg' => $message];
    }

    public static function success(string $m): void { self::add('success', $m); }
    public static function error(string $m): void { self::add('danger', $m); }
    public static function warning(string $m): void { self::add('warning', $m); }
    public static function info(string $m): void { self::add('info', $m); }

    public static function pull(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }
}
