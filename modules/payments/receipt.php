<?php
use App\Core\{Http, Router, View};
use App\Services\FeeService;

$id = Http::intParam('id');
$p = $id ? FeeService::payment($id) : null;
if (!$p) {
    Router::notFound();
}
if (Http::intParam('print')) {
    View::print('payments/receipt_print', ['p' => $p], 'Receipt ' . $p['receipt_no']);
    exit;
}
View::page('payments/receipt', ['p' => $p], 'Receipt ' . $p['receipt_no']);
