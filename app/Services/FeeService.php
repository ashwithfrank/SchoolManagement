<?php
namespace App\Services;

use App\Core\{Audit, Auth, Dates, Db, Flash, Money, Pager, Settings, UserError};

/**
 * Fees: structure, per-student assessments, discounts, payments and reversals.
 * Total assessed = academic + transport - discounts.  Balance = total assessed - valid payments.
 * All arithmetic is in integer paise.
 */
class FeeService
{
    public const CATEGORIES = ['academic' => 'Academic fee', 'transport' => 'Transport fee'];
    public const METHODS = ['cash' => 'Cash', 'upi' => 'UPI', 'cheque' => 'Cheque', 'bank_transfer' => 'Bank transfer', 'card' => 'Card', 'other' => 'Other'];

    // ---------- pure calculation (unit tested without a database) ----------

    /**
     * @param array $in keys: academic, transport, discount_academic, discount_transport, paid_academic, paid_transport (paise)
     */
    public static function compute(array $in): array
    {
        $out = [];
        foreach (['academic', 'transport'] as $c) {
            $assessed = (int) ($in[$c] ?? 0);
            $disc = (int) ($in['discount_' . $c] ?? 0);
            $paid = (int) ($in['paid_' . $c] ?? 0);
            $out[$c] = ['assessed' => $assessed, 'discount' => $disc, 'net' => $assessed - $disc, 'paid' => $paid, 'balance' => $assessed - $disc - $paid];
        }
        $out['total'] = [
            'assessed' => $out['academic']['assessed'] + $out['transport']['assessed'],
            'discount' => $out['academic']['discount'] + $out['transport']['discount'],
            'net'      => $out['academic']['net'] + $out['transport']['net'],
            'paid'     => $out['academic']['paid'] + $out['transport']['paid'],
            'balance'  => $out['academic']['balance'] + $out['transport']['balance'],
        ];
        $net = $out['total']['net'];
        $paid = $out['total']['paid'];
        $out['status'] = $net <= 0 ? ($paid > 0 ? 'credit' : 'none') : ($paid <= 0 ? 'pending' : ($paid >= $net ? 'paid' : 'partial'));
        return $out;
    }

    public static function statusLabel(string $s): array
    {
        return [
            'paid' => ['Paid', 'success'], 'partial' => ['Partly paid', 'warning'], 'pending' => ['Pending', 'danger'],
            'none' => ['Nothing assessed', 'secondary'], 'credit' => ['Credit', 'info'],
        ][$s] ?? [$s, 'secondary'];
    }

    // ---------- structure & assessments ----------

    public static function structure(int $yearId): array
    {
        return Db::all('SELECT c.id AS class_id, c.name AS class_name, fs.academic_fee, fs.updated_at
                          FROM classes c LEFT JOIN fee_structures fs ON fs.class_id = c.id AND fs.academic_year_id = ? ORDER BY c.sort_order', [$yearId]);
    }

    /** @param array<int,?int> $fees class_id => paise (null/'' = leave unset) */
    public static function saveStructure(int $yearId, array $fees, bool $updateExisting): array
    {
        Auth::require('fees.structure');
        if (!AcademicService::year($yearId)) {
            throw new UserError('Academic year not found.');
        }
        $stats = ['saved' => 0, 'created' => 0, 'updated' => 0];
        Db::tx(function () use ($yearId, $fees, $updateExisting, &$stats) {
            foreach ($fees as $classId => $paise) {
                if ($paise === null) {
                    continue;
                }
                Db::exec('INSERT INTO fee_structures (academic_year_id, class_id, academic_fee, updated_by, updated_at) VALUES (?, ?, ?, ?, NOW())
                          ON DUPLICATE KEY UPDATE academic_fee = VALUES(academic_fee), updated_by = VALUES(updated_by), updated_at = NOW()',
                    [$yearId, (int) $classId, Money::toDb($paise), Auth::id()]);
                $stats['saved']++;
            }
            // Create assessments for enrolled students that have none yet.
            $stats['created'] = Db::exec("INSERT INTO student_fee_assessments (student_id, academic_year_id, category, amount, source, created_by, created_at, updated_at)
                SELECT e.student_id, e.academic_year_id, 'academic', fs.academic_fee, 'structure', ?, NOW(), NOW()
                  FROM enrollments e JOIN fee_structures fs ON fs.academic_year_id = e.academic_year_id AND fs.class_id = e.class_id
                 WHERE e.academic_year_id = ? AND e.status <> 'left'
                   AND NOT EXISTS (SELECT 1 FROM student_fee_assessments a WHERE a.student_id = e.student_id AND a.academic_year_id = e.academic_year_id AND a.category = 'academic')",
                [Auth::id(), $yearId]);
            if ($updateExisting) {
                // Only structure-based assessments with no payments and no discounts are refreshed.
                $stats['updated'] = Db::exec("UPDATE student_fee_assessments a
                    JOIN enrollments e ON e.student_id = a.student_id AND e.academic_year_id = a.academic_year_id
                    JOIN fee_structures fs ON fs.academic_year_id = a.academic_year_id AND fs.class_id = e.class_id
                    SET a.amount = fs.academic_fee, a.updated_at = NOW()
                  WHERE a.academic_year_id = ? AND a.category = 'academic' AND a.source = 'structure' AND a.amount <> fs.academic_fee
                    AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.student_id = a.student_id AND p.academic_year_id = a.academic_year_id AND p.category = 'academic' AND p.status = 'valid')
                    AND NOT EXISTS (SELECT 1 FROM fee_adjustments d WHERE d.student_id = a.student_id AND d.academic_year_id = a.academic_year_id AND d.category = 'academic' AND d.status = 'active')", [$yearId]);
            }
            Audit::log('fees.structure', 'academic_year', $yearId, $stats);
        });
        return $stats;
    }

    public static function ensureAssessment(int $studentId, int $yearId, int $classId): void
    {
        $fee = Db::val('SELECT academic_fee FROM fee_structures WHERE academic_year_id = ? AND class_id = ?', [$yearId, $classId]);
        if ($fee === null) {
            return;
        }
        Db::exec("INSERT IGNORE INTO student_fee_assessments (student_id, academic_year_id, category, amount, source, created_by, created_at, updated_at)
                  VALUES (?, ?, 'academic', ?, 'structure', ?, NOW(), NOW())", [$studentId, $yearId, $fee, Auth::id()]);
    }

    /** After a class change: refresh a structure-based assessment unless money has already been paid against it. */
    public static function resyncAssessment(int $studentId, int $yearId, int $classId): void
    {
        $a = Db::row("SELECT * FROM student_fee_assessments WHERE student_id = ? AND academic_year_id = ? AND category = 'academic'", [$studentId, $yearId]);
        $fee = Db::val('SELECT academic_fee FROM fee_structures WHERE academic_year_id = ? AND class_id = ?', [$yearId, $classId]);
        if (!$a) {
            self::ensureAssessment($studentId, $yearId, $classId);
            return;
        }
        if ($a['source'] !== 'structure' || $fee === null || Money::fromDb($fee) === Money::fromDb($a['amount'])) {
            return;
        }
        $paid = Db::val("SELECT COUNT(*) FROM payments WHERE student_id = ? AND academic_year_id = ? AND category = 'academic' AND status = 'valid'", [$studentId, $yearId]);
        if ($paid > 0) {
            Flash::warning('The class changed, but academic fee payments already exist, so the fee assessment was left as it was. Review it on the fee statement.');
            return;
        }
        Db::exec('UPDATE student_fee_assessments SET amount = ?, updated_at = NOW() WHERE id = ?', [$fee, $a['id']]);
        Audit::log('assessment.resync', 'student', $studentId, ['year_id' => $yearId, 'class_id' => $classId]);
    }

    public static function setAssessment(int $studentId, int $yearId, int $amountPaise, string $reason): void
    {
        Auth::require('fees.assess');
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new UserError('Give a reason for changing the assessment.');
        }
        if ($amountPaise < 0) {
            throw new UserError('Amount cannot be negative.');
        }
        Db::tx(function () use ($studentId, $yearId, $amountPaise, $reason) {
            Db::row('SELECT id FROM students WHERE id = ? FOR UPDATE', [$studentId]);
            if (!StudentService::enrollmentFor($studentId, $yearId)) {
                throw new UserError('The student is not enrolled in that academic year.');
            }
            $s = self::summary($studentId, $yearId);
            if ($amountPaise - $s['academic']['discount'] < $s['academic']['paid']) {
                throw new UserError('That amount is lower than what has already been paid plus discounts. Reverse a payment or void a discount first.');
            }
            $old = $s['academic']['assessed'];
            Db::exec("INSERT INTO student_fee_assessments (student_id, academic_year_id, category, amount, source, note, created_by, created_at, updated_at)
                      VALUES (?, ?, 'academic', ?, 'manual', ?, ?, NOW(), NOW())
                      ON DUPLICATE KEY UPDATE amount = VALUES(amount), source = 'manual', note = VALUES(note), updated_at = NOW()",
                [$studentId, $yearId, Money::toDb($amountPaise), mb_substr($reason, 0, 200), Auth::id()]);
            Audit::log('assessment.set', 'student', $studentId, ['year_id' => $yearId, 'old' => Money::format($old), 'new' => Money::format($amountPaise), 'reason' => $reason]);
        });
    }

    // ---------- summary ----------

    public static function summary(int $studentId, int $yearId): array
    {
        $academic = Db::val("SELECT COALESCE(SUM(amount),0) FROM student_fee_assessments WHERE student_id = ? AND academic_year_id = ? AND category = 'academic'", [$studentId, $yearId]);
        $transport = Db::val("SELECT COALESCE(SUM(fee),0) FROM transport_assignments WHERE student_id = ? AND academic_year_id = ? AND status IN ('active','ended')", [$studentId, $yearId]);
        $in = ['academic' => Money::fromDb($academic), 'transport' => Money::fromDb($transport)];
        foreach (Db::all("SELECT category, COALESCE(SUM(amount),0) AS amt FROM fee_adjustments WHERE student_id = ? AND academic_year_id = ? AND status = 'active' GROUP BY category", [$studentId, $yearId]) as $r) {
            $in['discount_' . $r['category']] = Money::fromDb($r['amt']);
        }
        foreach (Db::all("SELECT category, COALESCE(SUM(amount),0) AS amt FROM payments WHERE student_id = ? AND academic_year_id = ? AND status = 'valid' GROUP BY category", [$studentId, $yearId]) as $r) {
            $in['paid_' . $r['category']] = Money::fromDb($r['amt']);
        }
        return self::compute($in);
    }

    // ---------- discounts ----------

    public static function adjustments(int $studentId, int $yearId): array
    {
        return Db::all('SELECT a.*, u.full_name AS created_by_name, v.full_name AS voided_by_name
                          FROM fee_adjustments a LEFT JOIN users u ON u.id = a.created_by LEFT JOIN users v ON v.id = a.voided_by
                         WHERE a.student_id = ? AND a.academic_year_id = ? ORDER BY a.id DESC', [$studentId, $yearId]);
    }

    public static function addDiscount(int $studentId, int $yearId, string $category, int $amountPaise, string $reason): int
    {
        Auth::require('fees.assess');
        $reason = trim($reason);
        if (!isset(self::CATEGORIES[$category])) {
            throw new UserError('Choose a fee category.');
        }
        if ($amountPaise <= 0) {
            throw new UserError('Discount must be greater than zero.');
        }
        if (mb_strlen($reason) < 3) {
            throw new UserError('Give a reason for the discount.');
        }
        return Db::tx(function () use ($studentId, $yearId, $category, $amountPaise, $reason) {
            Db::row('SELECT id FROM students WHERE id = ? FOR UPDATE', [$studentId]);
            $s = self::summary($studentId, $yearId);
            if ($amountPaise > $s[$category]['balance']) {
                throw new UserError('The discount cannot exceed the outstanding ' . strtolower(self::CATEGORIES[$category]) . ' (' . Money::format(max(0, $s[$category]['balance'])) . ').');
            }
            $id = Db::insert('INSERT INTO fee_adjustments (student_id, academic_year_id, category, amount, reason, status, created_by, created_at)
                              VALUES (?, ?, ?, ?, ?, \'active\', ?, NOW())', [$studentId, $yearId, $category, Money::toDb($amountPaise), mb_substr($reason, 0, 200), Auth::id()]);
            Audit::log('discount.add', 'student', $studentId, ['year_id' => $yearId, 'category' => $category, 'amount' => Money::format($amountPaise), 'reason' => $reason]);
            return $id;
        });
    }

    public static function voidDiscount(int $id, string $reason): void
    {
        Auth::require('fees.assess');
        if (mb_strlen(trim($reason)) < 3) {
            throw new UserError('Give a reason for voiding the discount.');
        }
        $n = Db::exec("UPDATE fee_adjustments SET status = 'void', voided_by = ?, voided_at = NOW(), void_reason = ? WHERE id = ? AND status = 'active'",
            [Auth::id(), mb_substr(trim($reason), 0, 200), $id]);
        if (!$n) {
            throw new UserError('That discount was not found or is already void.');
        }
        Audit::log('discount.void', 'fee_adjustment', $id, ['reason' => $reason]);
    }

    // ---------- payments ----------

    private static function nextReceiptNo(): string
    {
        Db::exec("INSERT INTO counters (name, value) VALUES ('receipt', 0) ON DUPLICATE KEY UPDATE value = value");
        $n = (int) Db::val("SELECT value FROM counters WHERE name = 'receipt' FOR UPDATE") + 1;
        Db::exec("UPDATE counters SET value = ? WHERE name = 'receipt'", [$n]);
        $prefix = preg_replace('/[^A-Za-z0-9-]/', '', Settings::get('receipt_prefix', 'RCT')) ?: 'RCT';
        return $prefix . '-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Record a payment. The _idem token makes the call idempotent: re-submitting the same form
     * returns the receipt that was already created instead of charging twice.
     * @return array{id:int, duplicate:bool}
     */
    public static function recordPayment(array $in): array
    {
        Auth::require('payments.record');
        $key = (string) ($in['idem'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            throw new UserError('This form is invalid or has expired. Reload the page and try again.');
        }
        $existing = Db::val('SELECT id FROM payments WHERE idempotency_key = ?', [$key]);
        if ($existing) {
            return ['id' => (int) $existing, 'duplicate' => true];
        }
        $studentId = (int) ($in['student_id'] ?? 0);
        $yearId = (int) ($in['year_id'] ?? 0);
        $category = (string) ($in['category'] ?? '');
        $amount = (int) ($in['amount'] ?? 0);
        $date = (string) ($in['payment_date'] ?? '');
        $method = (string) ($in['method'] ?? '');
        $ref = isset($in['reference_no']) && trim($in['reference_no']) !== '' ? mb_substr(trim($in['reference_no']), 0, 60) : null;
        $notes = isset($in['notes']) && trim($in['notes']) !== '' ? mb_substr(trim($in['notes']), 0, 300) : null;
        if (!isset(self::CATEGORIES[$category])) {
            throw new UserError('Choose a fee category.');
        }
        if (!isset(self::METHODS[$method])) {
            throw new UserError('Choose a payment method.');
        }
        if ($amount <= 0) {
            throw new UserError('Enter an amount greater than zero.');
        }
        if (!Dates::isValid($date) || $date > Dates::today()) {
            throw new UserError('Payment date must be a valid date that is not in the future.');
        }
        if ($method !== 'cash' && $ref === null) {
            throw new UserError('Enter the reference number (UPI/cheque/transaction number) for non-cash payments.');
        }
        try {
            return Db::tx(function () use ($key, $studentId, $yearId, $category, $amount, $date, $method, $ref, $notes) {
                $stu = Db::row('SELECT id, is_active FROM students WHERE id = ? FOR UPDATE', [$studentId]);
                if (!$stu) {
                    throw new UserError('Student not found.');
                }
                if (!StudentService::enrollmentFor($studentId, $yearId)) {
                    throw new UserError('The student is not enrolled in that academic year.');
                }
                $s = self::summary($studentId, $yearId);
                $bal = $s[$category]['balance'];
                if ($s[$category]['net'] <= 0) {
                    throw new UserError('Nothing is assessed for ' . strtolower(self::CATEGORIES[$category]) . ' for this student and year.');
                }
                if ($amount > $bal) {
                    throw new UserError('The amount is more than the outstanding ' . strtolower(self::CATEGORIES[$category]) . ' of ' . Money::format(max(0, $bal)) . '.');
                }
                $receipt = self::nextReceiptNo();
                $id = Db::insert('INSERT INTO payments (receipt_no, student_id, academic_year_id, category, amount, payment_date, method, reference_no, notes, status, idempotency_key, recorded_by, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'valid\', ?, ?, NOW())',
                    [$receipt, $studentId, $yearId, $category, Money::toDb($amount), $date, $method, $ref, $notes, $key, Auth::id()]);
                Audit::log('payment.record', 'payment', $id, ['receipt' => $receipt, 'student_id' => $studentId, 'amount' => Money::format($amount), 'category' => $category]);
                return ['id' => $id, 'duplicate' => false];
            });
        } catch (\PDOException $e) {
            if (Db::isDuplicate($e)) {
                // A parallel request with the same key won the race.
                $id = Db::val('SELECT id FROM payments WHERE idempotency_key = ?', [$key]);
                if ($id) {
                    return ['id' => (int) $id, 'duplicate' => true];
                }
            }
            throw $e;
        }
    }

    public static function reversePayment(int $paymentId, string $reason): void
    {
        Auth::require('payments.reverse');
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new UserError('Give a reason of at least 5 characters for the reversal.');
        }
        Db::tx(function () use ($paymentId, $reason) {
            $p = Db::row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$p) {
                throw new UserError('Payment not found.');
            }
            if ($p['status'] === 'reversed') {
                throw new UserError('This payment has already been reversed.');
            }
            Db::exec('INSERT INTO payment_reversals (payment_id, reason, reversed_by, reversed_at) VALUES (?, ?, ?, NOW())', [$paymentId, mb_substr($reason, 0, 300), Auth::id()]);
            Db::exec("UPDATE payments SET status = 'reversed' WHERE id = ?", [$paymentId]);
            Audit::log('payment.reverse', 'payment', $paymentId, ['receipt' => $p['receipt_no'], 'amount' => Money::format(Money::fromDb($p['amount'])), 'reason' => $reason]);
        });
    }

    public static function payment(int $id): ?array
    {
        return Db::row('SELECT p.*, s.full_name AS student_name, s.admission_no, s.father_name, s.guardian_name, y.name AS year_name,
                               c.name AS class_name, sec.name AS section_name, u.full_name AS recorded_by_name,
                               r.reason AS reversal_reason, r.reversed_at, ru.full_name AS reversed_by_name
                          FROM payments p
                          JOIN students s ON s.id = p.student_id JOIN academic_years y ON y.id = p.academic_year_id
                          LEFT JOIN enrollments e ON e.student_id = p.student_id AND e.academic_year_id = p.academic_year_id
                          LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN sections sec ON sec.id = e.section_id
                          LEFT JOIN users u ON u.id = p.recorded_by
                          LEFT JOIN payment_reversals r ON r.payment_id = p.id LEFT JOIN users ru ON ru.id = r.reversed_by
                         WHERE p.id = ?', [$id]);
    }

    public static function studentPayments(int $studentId, ?int $yearId = null): array
    {
        $sql = 'SELECT p.*, y.name AS year_name, u.full_name AS recorded_by_name FROM payments p
                  JOIN academic_years y ON y.id = p.academic_year_id LEFT JOIN users u ON u.id = p.recorded_by WHERE p.student_id = ?';
        $p = [$studentId];
        if ($yearId) {
            $sql .= ' AND p.academic_year_id = ?';
            $p[] = $yearId;
        }
        return Db::all($sql . ' ORDER BY p.payment_date DESC, p.id DESC', $p);
    }

    private static function paymentFilterSql(array $f): array
    {
        $where = ['1=1'];
        $p = [];
        if (!empty($f['year_id'])) { $where[] = 'p.academic_year_id = ?'; $p[] = (int) $f['year_id']; }
        if (!empty($f['category'])) { $where[] = 'p.category = ?'; $p[] = $f['category']; }
        if (!empty($f['method'])) { $where[] = 'p.method = ?'; $p[] = $f['method']; }
        if (!empty($f['status'])) { $where[] = 'p.status = ?'; $p[] = $f['status']; }
        if (!empty($f['from'])) { $where[] = 'p.payment_date >= ?'; $p[] = $f['from']; }
        if (!empty($f['to'])) { $where[] = 'p.payment_date <= ?'; $p[] = $f['to']; }
        if (!empty($f['class_id'])) { $where[] = 'e.class_id = ?'; $p[] = (int) $f['class_id']; }
        if (!empty($f['section_id'])) { $where[] = 'e.section_id = ?'; $p[] = (int) $f['section_id']; }
        if (!empty($f['q'])) {
            $like = StudentService::likeTerm($f['q']);
            $where[] = '(s.full_name LIKE ? OR s.admission_no LIKE ? OR p.receipt_no LIKE ? OR p.reference_no LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        return [implode(' AND ', $where), $p];
    }

    private const PAY_FROM = 'FROM payments p JOIN students s ON s.id = p.student_id
              LEFT JOIN enrollments e ON e.student_id = p.student_id AND e.academic_year_id = p.academic_year_id
              LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN sections sec ON sec.id = e.section_id
              LEFT JOIN users u ON u.id = p.recorded_by';

    public static function searchPayments(array $f, ?Pager &$pager = null, bool $all = false): array
    {
        Auth::require('fees.view');
        [$w, $p] = self::paymentFilterSql($f);
        $sql = 'SELECT p.*, s.full_name AS student_name, s.admission_no, c.name AS class_name, sec.name AS section_name, u.full_name AS recorded_by_name ' . self::PAY_FROM . " WHERE $w ORDER BY p.payment_date DESC, p.id DESC";
        if ($all) {
            return Db::all($sql, $p);
        }
        $pager = new Pager((int) Db::val("SELECT COUNT(*) " . self::PAY_FROM . " WHERE $w", $p), 25);
        return Db::all($sql . $pager->limitSql(), $p);
    }

    /** Totals for the same filters: valid collected vs reversed. */
    public static function paymentTotals(array $f): array
    {
        Auth::require('fees.view');
        [$w, $p] = self::paymentFilterSql($f);
        $r = Db::all("SELECT p.status, COUNT(*) AS n, COALESCE(SUM(p.amount),0) AS amt " . self::PAY_FROM . " WHERE $w GROUP BY p.status", $p);
        $out = ['valid' => ['n' => 0, 'amt' => 0], 'reversed' => ['n' => 0, 'amt' => 0]];
        foreach ($r as $x) {
            $out[$x['status']] = ['n' => (int) $x['n'], 'amt' => Money::fromDb($x['amt'])];
        }
        return $out;
    }

    // ---------- dues ----------

    private static function duesSql(int $yearId, array $f): array
    {
        $sql = "SELECT s.id AS student_id, s.full_name, s.admission_no, s.phone_primary, c.name AS class_name, c.sort_order, sec.name AS section_name,
                       COALESCE(a.amt,0) AS academic, COALESCE(t.amt,0) AS transport, COALESCE(d.amt,0) AS discount, COALESCE(p.amt,0) AS paid
                  FROM enrollments e
                  JOIN students s ON s.id = e.student_id
                  JOIN classes c ON c.id = e.class_id
                  JOIN sections sec ON sec.id = e.section_id
                  LEFT JOIN (SELECT student_id, SUM(amount) AS amt FROM student_fee_assessments WHERE academic_year_id = ? GROUP BY student_id) a ON a.student_id = s.id
                  LEFT JOIN (SELECT student_id, SUM(fee) AS amt FROM transport_assignments WHERE academic_year_id = ? AND status IN ('active','ended') GROUP BY student_id) t ON t.student_id = s.id
                  LEFT JOIN (SELECT student_id, SUM(amount) AS amt FROM fee_adjustments WHERE academic_year_id = ? AND status = 'active' GROUP BY student_id) d ON d.student_id = s.id
                  LEFT JOIN (SELECT student_id, SUM(amount) AS amt FROM payments WHERE academic_year_id = ? AND status = 'valid' GROUP BY student_id) p ON p.student_id = s.id
                 WHERE e.academic_year_id = ? AND e.status <> 'left' AND s.is_active = 1";
        $p = [$yearId, $yearId, $yearId, $yearId, $yearId];
        if (!empty($f['class_id'])) { $sql .= ' AND e.class_id = ?'; $p[] = (int) $f['class_id']; }
        if (!empty($f['section_id'])) { $sql .= ' AND e.section_id = ?'; $p[] = (int) $f['section_id']; }
        if (!empty($f['q'])) {
            $like = StudentService::likeTerm($f['q']);
            $sql .= ' AND (s.full_name LIKE ? OR s.admission_no LIKE ?)';
            array_push($p, $like, $like);
        }
        $outer = 'SELECT x.*, (x.academic + x.transport - x.discount) AS net, (x.academic + x.transport - x.discount - x.paid) AS balance FROM (' . $sql . ') x';
        $st = $f['status'] ?? '';
        if ($st === 'pending') {
            $outer .= ' WHERE (x.academic + x.transport - x.discount - x.paid) > 0';
        } elseif ($st === 'paid') {
            $outer .= ' WHERE (x.academic + x.transport - x.discount) > 0 AND (x.academic + x.transport - x.discount - x.paid) <= 0';
        } elseif ($st === 'unassessed') {
            $outer .= ' WHERE (x.academic + x.transport - x.discount) <= 0';
        }
        return [$outer, $p];
    }

    public static function dues(int $yearId, array $f, ?Pager &$pager = null, bool $all = false): array
    {
        Auth::require('fees.view');
        [$sql, $p] = self::duesSql($yearId, $f);
        $order = ' ORDER BY ' . (($f['sort'] ?? '') === 'balance' ? 'balance DESC, ' : '') . 'sort_order, section_name, full_name';
        if ($all) {
            return self::normalise(Db::all($sql . $order, $p));
        }
        $pager = new Pager((int) Db::val('SELECT COUNT(*) FROM (' . $sql . ') z', $p), 25);
        return self::normalise(Db::all($sql . $order . $pager->limitSql(), $p));
    }

    private static function normalise(array $rows): array
    {
        foreach ($rows as &$r) {
            foreach (['academic', 'transport', 'discount', 'paid', 'net', 'balance'] as $k) {
                $r[$k] = Money::fromDb($r[$k]);
            }
            $r['status'] = $r['net'] <= 0 ? 'none' : ($r['paid'] <= 0 ? 'pending' : ($r['balance'] <= 0 ? 'paid' : 'partial'));
        }
        return $rows;
    }

    public static function duesTotals(int $yearId, array $f): array
    {
        Auth::require('fees.view');
        [$sql, $p] = self::duesSql($yearId, $f);
        $r = Db::row('SELECT COUNT(*) AS n, COALESCE(SUM(net),0) AS net, COALESCE(SUM(paid),0) AS paid, COALESCE(SUM(balance),0) AS balance FROM (' . $sql . ') z', $p);
        return ['n' => (int) $r['n'], 'net' => Money::fromDb($r['net']), 'paid' => Money::fromDb($r['paid']), 'balance' => Money::fromDb($r['balance'])];
    }
}
