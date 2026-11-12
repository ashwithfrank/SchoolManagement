<?php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\{Auth, Http};
use App\Services\{PhotoService, StudentService};

if (!Auth::check() || !Auth::can('students.view')) {
    http_response_code(403);
    exit('Forbidden');
}
$id = Http::intParam('id');
$stu = $id ? StudentService::find($id) : null;
if (!$stu || !StudentService::canView($id) || !($path = PhotoService::path($stu['photo_file']))) {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: ' . PhotoService::contentType($stu['photo_file']));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="student-' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
readfile($path);
