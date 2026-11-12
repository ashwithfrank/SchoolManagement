<?php
namespace App\Core;

class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function path(): string
    {
        return APP_DIR . '/config/config.php';
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        if (self::exists()) {
            $d = require self::path();
            if (is_array($d)) {
                self::$data = $d;
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        $cur = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return $default;
            }
            $cur = $cur[$part];
        }
        return $cur;
    }
}
