<?php
use App\Core\{Flash, Http, UserError, View};
use App\Services\AcademicService;

$edit = null;
if (Http::isPost()) {
    try {
        if (Http::strParam('op') === 'toggle') {
            AcademicService::setSubjectActive((int) Http::intParam('id', 0), Http::intParam('active', 0) === 1);
            Flash::success('Subject updated.');
        } else {
            AcademicService::saveSubject(Http::intParam('id'), Http::strParam('name'), Http::strParam('code'));
            Flash::success('Subject saved.');
        }
        redirect(url('academics', 'subjects'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
$subjects = AcademicService::subjects();
if ($eid = Http::intParam('edit')) {
    foreach ($subjects as $s) {
        if ((int) $s['id'] === $eid) {
            $edit = $s;
        }
    }
}
View::page('academics/subjects', ['subjects' => $subjects, 'edit' => $edit], 'Subjects');
