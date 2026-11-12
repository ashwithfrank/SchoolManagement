<?php
use App\Core\{Flash, Http, UserError, View};
use App\Services\{AcademicService, EnrollmentService};

$years = AcademicService::years();
$from = Http::intParam('from');
$to = Http::intParam('to');
$class = Http::intParam('class');
$rows = null;
$results = $_SESSION['promo_results'] ?? null;
unset($_SESSION['promo_results']);

if (Http::isPost() && Http::strParam('step') === 'confirm') {
    try {
        $items = [];
        foreach ((array) ($_POST['row'] ?? []) as $eid => $r) {
            $items[] = ['enrollment_id' => (int) $eid, 'action' => (string) ($r['action'] ?? 'skip'), 'section_id' => (int) ($r['section'] ?? 0)];
        }
        if (!$items) {
            throw new UserError('Nothing to promote.');
        }
        $_SESSION['promo_results'] = EnrollmentService::promote((int) $from, (int) $to, $items);
        Flash::success('Promotion finished. Review the summary below.');
        redirect(url('academics', 'promotion', ['from' => $from, 'to' => $to]));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
if ($from && $to && !$results) {
    try {
        $rows = EnrollmentService::promotionPreview($from, $to, $class);
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('academics/promotion', [
    'years' => $years, 'from' => $from, 'to' => $to, 'class' => $class, 'rows' => $rows, 'results' => $results,
    'classes' => AcademicService::classes(), 'sections' => AcademicService::sections(),
], 'Promote students');
