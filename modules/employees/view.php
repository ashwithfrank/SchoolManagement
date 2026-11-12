<?php
use App\Core\{Auth, Db, Flash, Http, Router, UserError, View};
use App\Services\{AcademicService, EmployeeService};

$id = Http::intParam('id');
$emp = $id ? EmployeeService::find($id) : null;
if (!$emp) {
    Router::notFound();
}
if (Http::isPost()) {
    try {
        switch (Http::strParam('op')) {
            case 'subjects':
                EmployeeService::setSubjects($id, (array) ($_POST['subjects'] ?? []));
                Flash::success('Subjects saved.');
                break;
            case 'add_assign':
                EmployeeService::addAssignment($id, (int) Http::intParam('year_id', 0), (int) Http::intParam('section_id', 0), (int) Http::intParam('subject_id', 0));
                Flash::success('Assignment added.');
                break;
            case 'remove_assign':
                EmployeeService::removeAssignment((int) Http::intParam('assign_id', 0));
                Flash::success('Assignment removed.');
                break;
        }
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
    redirect(url('employees', 'view', ['id' => $id]));
}
$user = Db::row('SELECT u.username, u.is_active, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.employee_id = ?', [$id]);
View::page('employees/view', [
    'emp' => $emp, 'user' => $user, 'subjects' => AcademicService::subjects(true), 'mine' => EmployeeService::subjectIds($id),
    'assignments' => EmployeeService::assignments($id), 'coord' => EmployeeService::coordinatorOf($id),
    'sections' => AcademicService::sections(), 'years' => AcademicService::years(), 'year' => AcademicService::currentYear(),
], $emp['full_name']);
