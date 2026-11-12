<?php
namespace App\Core;

/**
 * Permission catalogue and the default role -> permission map.
 * The installer copies the defaults into the role_permissions table; the table is what is
 * enforced at run time. The Admin role always holds every permission (see Auth::can()).
 */
class Permissions
{
    public const ROLES = [
        'admin'    => 'Admin',
        'office'   => 'Office Staff',
        'teaching' => 'Teaching Staff',
    ];

    public const ALL = [
        'dashboard.view'     => 'Open the dashboard',
        'dashboard.finance'  => 'See fee totals on the dashboard',
        'students.view'      => 'View student profiles',
        'students.manage'    => 'Add/edit students, photos and enrollments',
        'academics.view'     => 'View academic years, classes, subjects',
        'academics.manage'   => 'Manage years, classes, sections, subjects, coordinators',
        'promotion.manage'   => 'Promote students to the next year',
        'employees.view'     => 'View employees',
        'employees.manage'   => 'Add/edit employees and assignments',
        'transport.view'     => 'View buses and transport assignments',
        'transport.assign'   => 'Assign students to buses and set their bus fee',
        'buses.manage'       => 'Add/edit buses',
        'fees.view'          => 'View fee statements and dues',
        'fees.structure'     => 'Set the fee structure per class',
        'fees.assess'        => 'Adjust assessments and add discounts',
        'payments.record'    => 'Record payments',
        'payments.reverse'   => 'Reverse payments',
        'exams.view'         => 'View exams, papers and results',
        'exams.manage'       => 'Create/edit exams',
        'exams.papers'       => 'Set maximum/minimum marks for papers',
        'marks.enter'        => 'Enter and update marks',
        'reports.students'   => 'Student, class and admission reports',
        'reports.finance'    => 'Financial reports',
        'reports.academic'   => 'Academic and marks reports',
        'reports.staff'      => 'Staff reports',
        'users.manage'       => 'Manage user accounts',
        'backups.manage'     => 'Create and restore backups',
        'settings.manage'    => 'Change application settings',
        'audit.view'         => 'View the audit log',
    ];

    public static function defaults(): array
    {
        $office = ['dashboard.view', 'dashboard.finance', 'students.view', 'students.manage', 'academics.view',
            'transport.view', 'transport.assign', 'fees.view', 'fees.assess', 'payments.record',
            'reports.students', 'reports.finance'];
        $teaching = ['dashboard.view', 'students.view', 'academics.view', 'exams.view', 'exams.papers',
            'marks.enter', 'reports.students', 'reports.academic'];
        return [
            'admin'    => array_keys(self::ALL),
            'office'   => $office,
            'teaching' => $teaching,
        ];
    }
}
