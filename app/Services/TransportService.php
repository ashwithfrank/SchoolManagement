<?php
namespace App\Services;

use App\Core\{Audit, Auth, Dates, Db, Flash, Money, Pager, Settings, UserError, Validator};

class TransportService
{
    // ---------- buses ----------

    public static function buses(bool $activeOnly = false): array
    {
        Auth::require('transport.view');
        $y = AcademicService::currentYear();
        $yid = $y ? (int) $y['id'] : 0;
        return Db::all('SELECT b.*, (SELECT COUNT(*) FROM transport_assignments t WHERE t.bus_id = b.id AND t.academic_year_id = ? AND t.status = \'active\') AS riders
                          FROM buses b' . ($activeOnly ? ' WHERE b.is_active = 1' : '') . ' ORDER BY b.bus_code', [$yid]);
    }

    public static function bus(int $id): ?array
    {
        return Db::row('SELECT * FROM buses WHERE id = ?', [$id]);
    }

    /** ['expired'|'soon'|'ok'|'none', days] for an expiry date. */
    public static function expiryState(?string $date): array
    {
        $d = Dates::daysUntil($date);
        if ($d === null) {
            return ['none', null];
        }
        $warn = (int) Settings::get('expiry_warning_days', '30');
        return [$d < 0 ? 'expired' : ($d <= $warn ? 'soon' : 'ok'), $d];
    }

    public static function saveBus(?int $id, array $in): int
    {
        Auth::require('buses.manage');
        $v = new Validator($in);
        $code = $v->text('bus_code', 'Bus ID', true, 20);
        $veh = $v->text('vehicle_no', 'Vehicle number', true, 20);
        $driver = $v->text('driver_name', 'Driver name', false, 120);
        $phone = $v->phone('driver_phone', 'Driver phone');
        $ins = $v->date('insurance_expiry', 'Insurance expiry');
        $fc = $v->date('fc_expiry', 'Fitness certificate expiry');
        $cap = $v->int('capacity', 'Seating capacity', false, 1, 200);
        $v->check();
        $veh = strtoupper(preg_replace('/\s+/', ' ', (string) $veh));
        try {
            if ($id) {
                Db::exec('UPDATE buses SET bus_code=?, vehicle_no=?, driver_name=?, driver_phone=?, insurance_expiry=?, fc_expiry=?, capacity=?, updated_at=NOW() WHERE id=?',
                    [$code, $veh, $driver, $phone, $ins, $fc, $cap, $id]);
                Audit::log('bus.update', 'bus', $id, ['vehicle' => $veh]);
                return $id;
            }
            $id = Db::insert('INSERT INTO buses (bus_code, vehicle_no, driver_name, driver_phone, insurance_expiry, fc_expiry, capacity, is_active, created_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())', [$code, $veh, $driver, $phone, $ins, $fc, $cap]);
            Audit::log('bus.create', 'bus', $id, ['vehicle' => $veh]);
            return $id;
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                throw new UserError('A bus with that Bus ID or vehicle number already exists.');
            }
            throw $e;
        }
    }

    public static function setBusActive(int $id, bool $active): void
    {
        Auth::require('buses.manage');
        Db::exec('UPDATE buses SET is_active = ?, updated_at = NOW() WHERE id = ?', [$active ? 1 : 0, $id]);
        Audit::log($active ? 'bus.activate' : 'bus.deactivate', 'bus', $id);
    }

    /** Buses with expired or soon-to-expire insurance / FC (active buses only). */
    public static function expiryAlerts(): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM buses WHERE is_active = 1 ORDER BY bus_code') as $b) {
            foreach (['insurance_expiry' => 'Insurance', 'fc_expiry' => 'Fitness certificate'] as $col => $label) {
                [$st, $days] = self::expiryState($b[$col]);
                if ($st === 'expired' || $st === 'soon') {
                    $out[] = ['bus' => $b, 'doc' => $label, 'date' => $b[$col], 'state' => $st, 'days' => $days];
                }
            }
        }
        usort($out, fn ($a, $b) => $a['days'] <=> $b['days']);
        return $out;
    }

    // ---------- assignments ----------

    public static function assignment(int $id): ?array
    {
        return Db::row('SELECT t.*, b.bus_code, b.vehicle_no, s.full_name AS student_name FROM transport_assignments t
                          JOIN buses b ON b.id = t.bus_id JOIN students s ON s.id = t.student_id WHERE t.id = ?', [$id]);
    }

    public static function search(int $yearId, array $f, ?Pager &$pager = null, bool $all = false): array
    {
        Auth::require('transport.view');
        $where = ['t.academic_year_id = ?'];
        $p = [$yearId];
        if (!empty($f['bus_id'])) { $where[] = 't.bus_id = ?'; $p[] = (int) $f['bus_id']; }
        $st = $f['status'] ?? 'active';
        if (in_array($st, ['active', 'ended', 'cancelled'], true)) { $where[] = 't.status = ?'; $p[] = $st; }
        if (!empty($f['q'])) {
            $like = StudentService::likeTerm($f['q']);
            $where[] = '(s.full_name LIKE ? OR s.admission_no LIKE ? OR t.pickup_location LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $from = "FROM transport_assignments t JOIN students s ON s.id = t.student_id JOIN buses b ON b.id = t.bus_id
                 LEFT JOIN enrollments e ON e.student_id = t.student_id AND e.academic_year_id = t.academic_year_id
                 LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN sections sec ON sec.id = e.section_id WHERE $w";
        $sql = "SELECT t.*, s.full_name AS student_name, s.admission_no, b.bus_code, b.vehicle_no, c.name AS class_name, sec.name AS section_name $from ORDER BY b.bus_code, s.full_name";
        if ($all) {
            return Db::all($sql, $p);
        }
        $pager = new Pager((int) Db::val("SELECT COUNT(*) $from", $p), 25);
        return Db::all($sql . $pager->limitSql(), $p);
    }

    public static function studentHistory(int $studentId): array
    {
        return Db::all('SELECT t.*, b.bus_code, b.vehicle_no, y.name AS year_name FROM transport_assignments t
                          JOIN buses b ON b.id = t.bus_id JOIN academic_years y ON y.id = t.academic_year_id
                         WHERE t.student_id = ? ORDER BY y.start_date DESC, t.id DESC', [$studentId]);
    }

    public static function assign(array $in): int
    {
        Auth::require('transport.assign');
        $v = new Validator($in);
        $studentId = $v->int('student_id', 'Student', true, 1);
        $yearId = $v->int('year_id', 'Academic year', true, 1);
        $busId = $v->int('bus_id', 'Bus', true, 1);
        $pickup = $v->text('pickup_location', 'Pickup/drop location', true, 150);
        $dist = null;
        if ($v->raw('distance_km') !== '') {
            $dist = Money::parseDecimal($v->raw('distance_km'));
            if ($dist === null) {
                $v->error('distance_km', 'Distance must be a number such as 7.5.');
            }
        }
        $fee = $v->money('fee', 'Bus fee', true, true);
        $start = $v->date('start_date', 'Start date', true);
        $v->check();
        $bus = self::bus($busId);
        if (!$bus || !$bus['is_active']) {
            throw new UserError('Choose an active bus.');
        }
        return Db::tx(function () use ($studentId, $yearId, $busId, $pickup, $dist, $fee, $start, $bus) {
            $stu = Db::row('SELECT id, full_name, is_active FROM students WHERE id = ? FOR UPDATE', [$studentId]);
            if (!$stu || !$stu['is_active']) {
                throw new UserError('Choose an active student.');
            }
            if (!StudentService::enrollmentFor($studentId, $yearId)) {
                throw new UserError('The student must be enrolled in that academic year before a bus can be assigned.');
            }
            if (Db::val("SELECT 1 FROM transport_assignments WHERE student_id = ? AND academic_year_id = ? AND status = 'active'", [$studentId, $yearId])) {
                throw new UserError('This student already has an active bus assignment for the year. End it first to change the bus.');
            }
            $id = Db::insert("INSERT INTO transport_assignments (student_id, academic_year_id, bus_id, pickup_location, distance_km, fee, start_date, status, active_flag, created_by, created_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1, ?, NOW(), NOW())",
                [$studentId, $yearId, $busId, $pickup, $dist, Money::toDb($fee), $start, Auth::id()]);
            Audit::log('transport.assign', 'transport_assignment', $id, ['student_id' => $studentId, 'bus' => $bus['bus_code'], 'fee' => Money::format($fee)]);
            if ($bus['capacity']) {
                $n = (int) Db::val("SELECT COUNT(*) FROM transport_assignments WHERE bus_id = ? AND academic_year_id = ? AND status = 'active'", [$busId, $yearId]);
                if ($n > (int) $bus['capacity']) {
                    Flash::warning('Bus ' . $bus['bus_code'] . ' now has ' . $n . ' students but seats ' . $bus['capacity'] . '.');
                }
            }
            return $id;
        });
    }

    /** Net fee the student must still keep covered after a change to the transport fee. */
    private static function assertFeeAllowed(array $a, int $newFee, ?int $excludeAssignmentFee = null): void
    {
        $s = FeeService::summary((int) $a['student_id'], (int) $a['academic_year_id']);
        $oldFee = Money::fromDb($a['fee']);
        $newAssessed = $s['transport']['assessed'] - $oldFee + $newFee;
        if ($newAssessed - $s['transport']['discount'] < $s['transport']['paid']) {
            throw new UserError('That fee is lower than the transport fee already paid plus discounts (' . Money::format($s['transport']['paid'] + $s['transport']['discount']) . '). Reverse a payment or void a discount first.');
        }
    }

    public static function updateFee(int $id, int $feePaise, ?string $pickup, ?string $distance): void
    {
        Auth::require('transport.assign');
        if ($feePaise < 0) {
            throw new UserError('Fee cannot be negative.');
        }
        $dist = null;
        if ($distance !== null && trim($distance) !== '') {
            $dist = Money::parseDecimal($distance);
            if ($dist === null) {
                throw new UserError('Distance must be a number such as 7.5.');
            }
        }
        Db::tx(function () use ($id, $feePaise, $pickup, $dist) {
            $a = Db::row('SELECT * FROM transport_assignments WHERE id = ? FOR UPDATE', [$id]);
            if (!$a || $a['status'] === 'cancelled') {
                throw new UserError('Assignment not found or cancelled.');
            }
            Db::row('SELECT id FROM students WHERE id = ? FOR UPDATE', [$a['student_id']]);
            self::assertFeeAllowed($a, $feePaise);
            Db::exec('UPDATE transport_assignments SET fee = ?, pickup_location = ?, distance_km = ?, updated_at = NOW() WHERE id = ?',
                [Money::toDb($feePaise), $pickup !== null && trim($pickup) !== '' ? mb_substr(trim($pickup), 0, 150) : $a['pickup_location'], $dist, $id]);
            Audit::log('transport.update', 'transport_assignment', $id, ['old_fee' => Money::format(Money::fromDb($a['fee'])), 'new_fee' => Money::format($feePaise)]);
        });
    }

    /** End an assignment (bus change / leaving). History is kept; the fee may be set to the amount actually due. */
    public static function end(int $id, string $endDate, string $reason, ?int $finalFeePaise): void
    {
        Auth::require('transport.assign');
        if (!Dates::isValid($endDate)) {
            throw new UserError('Enter a valid end date.');
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw new UserError('Give a reason (for example: changed bus, left transport).');
        }
        Db::tx(function () use ($id, $endDate, $reason, $finalFeePaise) {
            $a = Db::row('SELECT * FROM transport_assignments WHERE id = ? FOR UPDATE', [$id]);
            if (!$a || $a['status'] !== 'active') {
                throw new UserError('Only an active assignment can be ended.');
            }
            if ($endDate < $a['start_date']) {
                throw new UserError('The end date cannot be before the start date.');
            }
            Db::row('SELECT id FROM students WHERE id = ? FOR UPDATE', [$a['student_id']]);
            $fee = $finalFeePaise ?? Money::fromDb($a['fee']);
            self::assertFeeAllowed($a, $fee);
            Db::exec("UPDATE transport_assignments SET status = 'ended', active_flag = NULL, end_date = ?, end_reason = ?, fee = ?, updated_at = NOW() WHERE id = ?",
                [$endDate, mb_substr(trim($reason), 0, 200), Money::toDb($fee), $id]);
            Audit::log('transport.end', 'transport_assignment', $id, ['end_date' => $endDate, 'fee' => Money::format($fee), 'reason' => $reason]);
        });
    }

    /** Cancel an assignment entered by mistake: it stops counting toward fees but stays in history. */
    public static function cancel(int $id, string $reason): void
    {
        Auth::require('transport.assign');
        if (mb_strlen(trim($reason)) < 3) {
            throw new UserError('Give a reason for cancelling.');
        }
        Db::tx(function () use ($id, $reason) {
            $a = Db::row('SELECT * FROM transport_assignments WHERE id = ? FOR UPDATE', [$id]);
            if (!$a || $a['status'] === 'cancelled') {
                throw new UserError('Assignment not found or already cancelled.');
            }
            Db::row('SELECT id FROM students WHERE id = ? FOR UPDATE', [$a['student_id']]);
            self::assertFeeAllowed($a, 0);
            Db::exec("UPDATE transport_assignments SET status = 'cancelled', active_flag = NULL, end_reason = ?, updated_at = NOW() WHERE id = ?", [mb_substr(trim($reason), 0, 200), $id]);
            Audit::log('transport.cancel', 'transport_assignment', $id, ['reason' => $reason]);
        });
    }
}
