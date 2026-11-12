<?php
use App\Core\{Dates, Flash, Http, Money, UserError, View};
use App\Services\{AcademicService, FeeService, StudentService};

$year = AcademicService::currentYear();
if (!$year) {
    Flash::warning('Create an academic year first.');
    redirect(url('academics', 'years'));
}
$yid = (int) $year['id'];
$sid = Http::intParam('student');
$stu = $sid ? StudentService::find($sid) : null;
$d = ['payment_date' => Dates::today(), 'method' => 'cash'];
if ($stu && Http::isPost()) {
    $d = $_POST;
    try {
        $amount = Money::parse(Http::strParam('amount'));
        if ($amount === null) {
            throw new UserError('Enter the amount as a number such as 2500 or 2500.50.');
        }
        $r = FeeService::recordPayment([
            'idem' => Http::strParam('_idem'), 'student_id' => $sid, 'year_id' => $yid, 'category' => Http::strParam('category'), 'amount' => $amount,
            'payment_date' => Http::strParam('payment_date'), 'method' => Http::strParam('method'), 'reference_no' => Http::strParam('reference_no'), 'notes' => Http::strParam('notes'),
        ]);
        $r['duplicate'] ? Flash::info('This payment had already been recorded; showing the existing receipt. Nothing was charged twice.') : Flash::success('Payment recorded.');
        redirect(url('payments', 'receipt', ['id' => $r['id']]));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('payments/record', [
    'year' => $year, 'stu' => $stu, 'd' => $d, 'enrol' => $stu ? StudentService::enrollmentFor($sid, $yid) : null,
    'summary' => $stu ? FeeService::summary($sid, $yid) : null,
], 'Record payment');
