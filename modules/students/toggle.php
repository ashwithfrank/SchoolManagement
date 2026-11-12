<?php
use App\Core\{Flash, Http};
use App\Services\StudentService;

$id = Http::intParam('id');
if (Http::isPost() && $id) {
    StudentService::setActive($id, Http::intParam('active', 0) === 1);
    Flash::success('Student status updated.');
}
redirect(url('students', 'view', ['id' => $id]));
