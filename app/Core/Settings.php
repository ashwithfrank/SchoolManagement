<?php
namespace App\Core;

class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        'school_name'            => 'My School',
        'school_address'         => '',
        'school_phone'           => '',
        'receipt_prefix'         => 'RCT',
        'receipt_footer'         => 'This is a computer generated receipt.',
        'teachers_all_marks'     => '0',
        'expiry_warning_days'    => '30',
        'backup_dir'             => '',
        'backup_keep_note'       => '',
        'cloud_sync_dir'         => '',
        'photo_max_mb'           => '5',
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            foreach (Db::all('SELECT setting_key, setting_value FROM app_settings') as $r) {
                self::$cache[$r['setting_key']] = (string) $r['setting_value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $a = self::all();
        return $a[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Db::exec('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
        self::$cache = null;
    }
}
