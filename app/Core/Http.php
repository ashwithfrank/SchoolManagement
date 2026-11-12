<?php
namespace App\Core;

class Http
{
    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function intParam(string $key, ?int $default = null): ?int
    {
        $v = $_GET[$key] ?? $_POST[$key] ?? null;
        if ($v === null || $v === '' || is_array($v) || !preg_match('/^\d{1,10}$/', (string) $v)) {
            return $default;
        }
        return (int) $v;
    }

    public static function strParam(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $_POST[$key] ?? $default;
        return is_array($v) ? $default : trim((string) $v);
    }

    /** Stream a CSV download (UTF-8 with BOM so Excel opens it correctly). */
    public static function csv(string $filename, array $header, iterable $rows): void
    {
        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map([self::class, 'csvSafe'], $header), ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($out, array_map([self::class, 'csvSafe'], array_values($r)), ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /** Neutralise spreadsheet formula injection. */
    public static function csvSafe($v): string
    {
        $s = (string) ($v ?? '');
        if ($s !== '' && strpos("=+-@\t\r", $s[0]) !== false && !is_numeric($s)) {
            return "'" . $s;
        }
        return $s;
    }
}
