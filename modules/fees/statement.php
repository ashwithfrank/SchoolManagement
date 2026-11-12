<?php
use App\Core\{Flash, Http, Money, Router, UserError, View};
use App\Services\{AcademicService, FeeService, StudentService, TransportService};

$sid = Http::intParam('student');
$stu = $sid ? StudentService::find($sid) : null;
$year = AcademicService::currentYear();
if (!$stu || !$year) {
    Router::notFound();
}
$yid = (int) $year['id'];
if (Http::isPost()) {
    try {
        switch (Http::strParam('op')) {
            case 'assessment':
                $amt = Money::parse(Http::strParam('amount'));
                if ($amt === null) {
                    throw new UserError('Enter the academic fee as an amount.');
                }
                FeeService::setAssessment($sid, $yid, $amt, Http::strParam('reason'));
                Flash::success('Academic fee assessment updated.');
                break;
            case 'discount':
                $amt = Money::parse(Http::strParam('amount'));
                if ($amt === null) {
                    throw new UserError('Enter the discount as an amount.');
                }
                FeeService::addDiscount($sid, $yid, Http::strParam('category'), $amt, Http::strParam('reason'));
                Flash::success('Discount recorded.');
                break;
            case 'void':
                FeeService::voidDiscount((int) Http::intParam('adj_id', 0), Http::strParam('reason'));
                Flash::success('Discount voided. It remains visible in the history.');
                break;
        }
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
    redirect(url('fees', 'statement', ['student' => $sid]));
}
$enrol = StudentService::enrollmentFor($sid, $yid);
$data = [
    'stu' => $stu, 'year' => $year, 'enrol' => $enrol,
    'summary' => FeeService::summary($sid, $yid), 'adjustments' => FeeService::adjustments($sid, $yid),
    'payments' => FeeService::studentPayments($sid, $yid),
    'transport' => array_filter(TransportService::studentHistory($sid), fn ($t) => (int) $t['academic_year_id'] === $yid),
];
if (Http::intParam('print')) {
    View::print('fees/statement_print', $data, 'Fee statement - ' . $stu['full_name']);
    exit;
}
View::page('fees/statement', $data, 'Fee statement');
