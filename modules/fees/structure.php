<?php
use App\Core\{Flash, Http, Money, UserError, View};
use App\Services\{AcademicService, FeeService};

$year = AcademicService::currentYear();
if (!$year) {
    Flash::warning('Create an academic year first.');
    redirect(url('academics', 'years'));
}
if (Http::isPost()) {
    try {
        $fees = [];
        foreach ((array) ($_POST['fee'] ?? []) as $classId => $val) {
            $val = trim((string) $val);
            if ($val === '') {
                $fees[(int) $classId] = null;
                continue;
            }
            $p = Money::parse($val);
            if ($p === null) {
                throw new UserError('Fees must be amounts such as 24000 or 24000.50.');
            }
            $fees[(int) $classId] = $p;
        }
        $r = FeeService::saveStructure((int) $year['id'], $fees, !empty($_POST['update_existing']));
        Flash::success("Fee structure saved ({$r['saved']} classes). New assessments created: {$r['created']}; refreshed: {$r['updated']}.");
        redirect(url('fees', 'structure'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('fees/structure', ['year' => $year, 'rows' => FeeService::structure((int) $year['id'])], 'Fee structure');
