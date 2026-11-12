<?php
use App\Core\{Flash, Http, UserError, View};
use App\Services\{AcademicService, EmployeeService};

$year = AcademicService::currentYear();
if (!$year) {
    Flash::warning('Create an academic year first.');
    redirect(url('academics', 'years'));
}
if (Http::isPost()) {
    try {
        if (Http::strParam('op') === 'remove') {
            AcademicService::removeCoordinator((int) Http::intParam('id', 0));
            Flash::success('Coordinator removed.');
        } else {
            AcademicService::setCoordinator((int) $year['id'], (int) Http::intParam('section_id', 0), (int) Http::intParam('employee_id', 0));
            Flash::success('Coordinator saved.');
        }
        redirect(url('academics', 'coordinators'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('academics/coordinators', ['year' => $year, 'rows' => AcademicService::coordinators((int) $year['id']),
    'sections' => AcademicService::sections(), 'employees' => can('employees.view') || can('academics.manage') ? EmployeeService::activeList() : []], 'Class coordinators');
