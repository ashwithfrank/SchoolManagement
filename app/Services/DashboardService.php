<?php
namespace App\Services;

use App\Core\{Auth, Db, Money};

class DashboardService
{
    public static function data(): array
    {
        $year = AcademicService::currentYear();
        $yid = $year ? (int) $year['id'] : 0;
        $d = ['year' => $year, 'active_year' => AcademicService::activeYear()];
        $scope = Scope::sectionIds($yid);

        if ($scope === null) {
            $d['students'] = (int) Db::val("SELECT COUNT(*) FROM students s JOIN enrollments e ON e.student_id = s.id AND e.academic_year_id = ? AND e.status <> 'left' WHERE s.is_active = 1", [$yid]);
            $d['students_total'] = (int) Db::val('SELECT COUNT(*) FROM students WHERE is_active = 1');
        } elseif ($scope) {
            [$in, $p] = Db::in($scope);
            $d['students'] = (int) Db::val("SELECT COUNT(*) FROM enrollments e JOIN students s ON s.id = e.student_id WHERE e.academic_year_id = ? AND e.status <> 'left' AND s.is_active = 1 AND e.section_id IN ($in)", array_merge([$yid], $p));
            $d['students_total'] = $d['students'];
        } else {
            $d['students'] = $d['students_total'] = 0;
        }
        $d['staff'] = (int) Db::val('SELECT COUNT(*) FROM employees WHERE is_active = 1');
        $d['sections_assigned'] = $scope === null ? null : count($scope);

        if (Auth::can('dashboard.finance')) {
            $d['collected'] = Money::fromDb(Db::val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE academic_year_id = ? AND status = 'valid'", [$yid]));
            $t = FeeService::duesTotals($yid, []);
            $d['outstanding'] = max(0, $t['balance']);
            $d['net_assessed'] = $t['net'];
            $d['recent_payments'] = Db::all("SELECT p.id, p.receipt_no, p.amount, p.payment_date, p.category, p.status, s.full_name AS student_name
                                               FROM payments p JOIN students s ON s.id = p.student_id ORDER BY p.id DESC LIMIT 6");
        }
        if (Auth::can('transport.view')) {
            $d['transport_students'] = (int) Db::val("SELECT COUNT(DISTINCT student_id) FROM transport_assignments WHERE academic_year_id = ? AND status = 'active'", [$yid]);
            $d['bus_alerts'] = TransportService::expiryAlerts();
        }
        if ($scope === null) {
            $d['recent_admissions'] = Db::all('SELECT s.id, s.full_name, s.admission_no, s.admission_date, c.name AS class_name, sec.name AS section_name
                                                 FROM students s LEFT JOIN enrollments e ON e.student_id = s.id AND e.academic_year_id = ?
                                                 LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN sections sec ON sec.id = e.section_id
                                                ORDER BY s.id DESC LIMIT 6', [$yid]);
        }
        if (Auth::can('audit.view')) {
            $d['recent_activity'] = Db::all('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 8');
        }
        return $d;
    }
}
