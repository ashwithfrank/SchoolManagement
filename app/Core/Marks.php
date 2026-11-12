<?php
namespace App\Core;

/** Marks arithmetic. Values are DECIMAL strings (max 2 places); computed in hundredths to avoid float drift. */
class Marks
{
    public static function hundredths($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = (string) $v;
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }
        [$a, $b] = array_pad(explode('.', $s, 2), 2, '0');
        return ((int) $a) * 100 + (int) str_pad($b, 2, '0');
    }

    /** "85.5" / "85.50" -> "85.5" ; "90.00" -> "90" for display. */
    public static function display($v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $s = (string) $v;
        return strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    /**
     * @return array{state:string, percentage:?float, result:string}
     *   state: 'absent' | 'entered' | 'missing'; result: 'Pass' | 'Fail' | 'Absent' | 'Not entered'
     */
    public static function evaluate($scored, bool $absent, $max, $min): array
    {
        if ($absent) {
            return ['state' => 'absent', 'percentage' => null, 'result' => 'Absent'];
        }
        $s = self::hundredths($scored);
        $mx = self::hundredths($max);
        $mn = self::hundredths($min);
        if ($s === null || $mx === null || $mx <= 0) {
            return ['state' => 'missing', 'percentage' => null, 'result' => 'Not entered'];
        }
        return [
            'state'      => 'entered',
            'percentage' => round($s * 100 / $mx, 2),
            'result'     => ($mn !== null && $s >= $mn) ? 'Pass' : 'Fail',
        ];
    }

    /**
     * Overall result across subjects. Any absent/missing subject makes the overall result incomplete.
     * @param array<int,array> $lines each: ['scored'=>, 'absent'=>bool, 'max'=>, 'min'=>]
     */
    public static function overall(array $lines): array
    {
        $tot = 0;
        $max = 0;
        $fail = 0;
        $incomplete = 0;
        foreach ($lines as $l) {
            $e = self::evaluate($l['scored'] ?? null, (bool) ($l['absent'] ?? false), $l['max'] ?? null, $l['min'] ?? null);
            if ($e['state'] !== 'entered') {
                $incomplete++;
                continue;
            }
            $tot += self::hundredths($l['scored']);
            $max += self::hundredths($l['max']);
            if ($e['result'] === 'Fail') {
                $fail++;
            }
        }
        $pct = $max > 0 ? round($tot * 100 / $max, 2) : null;
        $result = $lines === [] ? 'No papers' : ($fail > 0 ? 'Fail' : ($incomplete > 0 ? 'Incomplete' : 'Pass'));
        return ['total' => $tot / 100, 'max' => $max / 100, 'percentage' => $pct, 'result' => $result, 'failed' => $fail, 'incomplete' => $incomplete];
    }
}
