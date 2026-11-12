<?php
use App\Core\{Flash, Http, UserError};
use App\Services\PhotoService;

$id = Http::intParam('id');
if (!Http::isPost() || !$id) {
    redirect(url('students', 'list'));
}
try {
    if (Http::strParam('op') === 'remove') {
        PhotoService::remove($id);
        Flash::success('Photo removed.');
    } else {
        PhotoService::save($id, $_FILES['photo'] ?? []);
        Flash::success('Photo saved.');
    }
} catch (UserError $e) {
    Flash::error($e->getMessage());
}
redirect(url('students', 'view', ['id' => $id]));
