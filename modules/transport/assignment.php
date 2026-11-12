<?php
use App\Core\{Dates, Flash, Http, Money, Router, UserError, View};
use App\Services\TransportService;

$id = Http::intParam('id');
$a = $id ? TransportService::assignment($id) : null;
if (!$a) {
    Router::notFound();
}
if (Http::isPost()) {
    try {
        switch (Http::strParam('op')) {
            case 'update':
                $fee = Money::parse(Http::strParam('fee'));
                if ($fee === null) {
                    throw new UserError('Enter the fee as an amount such as 12000 or 12000.50.');
                }
                TransportService::updateFee($id, $fee, Http::strParam('pickup_location'), Http::strParam('distance_km'));
                Flash::success('Transport details updated.');
                break;
            case 'end':
                $final = Http::strParam('final_fee') === '' ? null : Money::parse(Http::strParam('final_fee'));
                if (Http::strParam('final_fee') !== '' && $final === null) {
                    throw new UserError('Enter the final fee as an amount.');
                }
                TransportService::end($id, Http::strParam('end_date'), Http::strParam('reason'), $final);
                Flash::success('Assignment ended. It stays in the history.');
                break;
            case 'cancel':
                TransportService::cancel($id, Http::strParam('reason'));
                Flash::success('Assignment cancelled.');
                break;
        }
        redirect(url('transport', 'assignments'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
        $a = TransportService::assignment($id);
    }
}
View::page('transport/assignment', ['a' => $a, 'today' => Dates::today()], 'Manage transport assignment');
