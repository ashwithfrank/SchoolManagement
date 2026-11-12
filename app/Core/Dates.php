<?php
namespace App\Core;

class Dates
{
    public static function isValid(?string $ymd): bool
    {
        if ($ymd === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** 2026-04-05 -> 05 Apr 2026 (empty string for blank) */
    public static function display(?string $ymd): string
    {
        if (!$ymd || !self::isValid(substr($ymd, 0, 10))) {
            return '';
        }
        return date('d M Y', strtotime(substr($ymd, 0, 10)));
    }

    public static function displayTime(?string $ts): string
    {
        if (!$ts) {
            return '';
        }
        $t = strtotime($ts);
        return $t ? date('d M Y, h:i A', $t) : '';
    }

    public static function today(): string
    {
        return date('Y-m-d');
    }

    public static function daysUntil(?string $ymd): ?int
    {
        if (!$ymd || !self::isValid($ymd)) {
            return null;
        }
        $a = new \DateTimeImmutable(self::today());
        $b = new \DateTimeImmutable($ymd);
        return (int) $a->diff($b)->format('%r%a');
    }
}
