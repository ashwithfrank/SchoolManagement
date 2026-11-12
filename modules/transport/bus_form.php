<?php
use App\Core\{Flash, Http, Router, UserError, View};
use App\Services\TransportService;

$id = Http::intParam('id');
$bus = $id ? TransportService::bus($id) : null;
if ($id && !$bus) {
    Router::notFound();
}
$d = $bus ?? [];
if (Http::isPost()) {
    $d = $_POST;
    try {
        if (Http::strParam('op') === 'toggle' && $id) {
            TransportService::setBusActive($id, Http::intParam('active', 0) === 1);
            Flash::success('Bus status updated.');
        } else {
            TransportService::saveBus($id, $_POST);
            Flash::success('Bus saved.');
        }
        redirect(url('transport', 'buses'));
    } catch (UserError $e) {
        Flash::error($e->getMessage());
    }
}
View::page('transport/bus_form', ['bus' => $bus, 'd' => $d], $bus ? 'Edit bus' : 'Add bus');
