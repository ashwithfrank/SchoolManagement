<?php
namespace App\Core;

/**
 * Money is handled as integer paise in PHP and stored as DECIMAL(12,2) in MySQL,
 * so no floating-point arithmetic ever touches a rupee amount.
 */
class Money
{
    /** Parse user input such as "1,250.50" or "1250". Returns paise, or null when invalid. */
    public static function parse(?string $input): ?int
    {
        $s = trim((string) $input);
        $s = str_replace([',', ' ', '₹'], '', $s);
        if ($s === '' || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $s)) {
            return null;
        }
        [$r, $p] = array_pad(explode('.', $s, 2), 2, '0');
        return ((int) $r) * 100 + (int) str_pad($p, 2, '0');
    }

    /** From a DECIMAL string such as "1234.50" (as returned by MySQL). */
    public static function fromDb($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $s = (string) $value;
        $neg = $s[0] === '-';
        $s = ltrim($s, '-+');
        [$r, $p] = array_pad(explode('.', $s, 2), 2, '0');
        $v = ((int) $r) * 100 + (int) substr(str_pad($p, 2, '0'), 0, 2);
        return $neg ? -$v : $v;
    }

    /** To a DECIMAL string for binding: 123450 -> "1234.50" */
    public static function toDb(int $paise): string
    {
        $neg = $paise < 0;
        $paise = abs($paise);
        return ($neg ? '-' : '') . intdiv($paise, 100) . '.' . str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Indian digit grouping: 1234567.5 -> "₹ 12,34,567.50" */
    public static function format(int $paise, bool $symbol = true): string
    {
        $neg = $paise < 0;
        $paise = abs($paise);
        $rupees = (string) intdiv($paise, 100);
        $dec = str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
        if (strlen($rupees) > 3) {
            $last3 = substr($rupees, -3);
            $rest = substr($rupees, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $rupees = $rest . ',' . $last3;
        }
        return ($neg ? '-' : '') . ($symbol ? '₹ ' : '') . $rupees . '.' . $dec;
    }

    /** Marks-style decimal (up to 2 places) -> DECIMAL string or null if invalid / negative. */
    public static function parseDecimal(?string $input): ?string
    {
        $s = trim((string) $input);
        if ($s === '' || !preg_match('/^\d{1,4}(\.\d{1,2})?$/', $s)) {
            return null;
        }
        return $s;
    }
}
