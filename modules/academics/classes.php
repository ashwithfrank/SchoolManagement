<?php
use App\Core\{Flash, Http, UserError, View, Db};
use App\Services\AcademicService;

if (Http::isPost()) {
    try {
        if (Http::strParam('op') === 'toggle') {
            AcademicService::setSectionActive((int) Http::intParam('id', 0), Http::intParam('active', 0) === 1);
            Flash::success('Section updated.');
        } else {
            AcademicService::addSection((int) Http::intParam('class_id', 0), Http::strParam('name'));
            Flash::success('Section added.');
        }
        redirect(url('academics', 'classes'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
$year = AcademicService::currentYear();
$counts = [];
if ($year) {
    foreach (Db::all("SELECT section_id, COUNT(*) AS n FROM enrollments WHERE academic_year_id = ? AND status <> 'left' GROUP BY section_id", [$year['id']]) as $r) {
        $counts[(int) $r['section_id']] = (int) $r['n'];
    }
}
$by = [];
foreach (AcademicService::sections(null, false) as $s) {
    $by[(int) $s['class_id']][] = $s;
}
$coords = [];
if ($year) {
    foreach (AcademicService::coordinators((int) $year['id']) as $c) {
        $coords[(int) $c['section_id']] = $c;
    }
}
View::page('academics/classes', ['classes' => AcademicService::classes(), 'by' => $by, 'counts' => $counts, 'year' => $year, 'coords' => $coords], 'Classes & sections');
