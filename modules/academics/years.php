<?php
use App\Core\{Flash, Http, UserError, View};
use App\Services\AcademicService;

if (Http::isPost()) {
    try {
        if (Http::strParam('op') === 'activate') {
            AcademicService::setActive((int) Http::intParam('id', 0));
            Flash::success('Active academic year changed.');
        } else {
            AcademicService::createYear(Http::strParam('name'), Http::strParam('start_date'), Http::strParam('end_date'), !empty($_POST['make_active']));
            Flash::success('Academic year created.');
        }
        redirect(url('academics', 'years'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
$years = AcademicService::years();
foreach ($years as &$y) {
    $y['students'] = (int) \App\Core\Db::val("SELECT COUNT(*) FROM enrollments WHERE academic_year_id = ?", [$y['id']]);
}
unset($y);
View::page('academics/years', ['years' => $years], 'Academic years');
