<?php
use App\Core\{Flash, Http, UserError};
use App\Services\FeeService;

$id = Http::intParam('id');
if (Http::isPost() && $id) {
    try {
        FeeService::reversePayment($id, Http::strParam('reason'));
        Flash::success('Payment reversed. The receipt remains in the records.');
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
redirect(url('payments', 'receipt', ['id' => $id]));
