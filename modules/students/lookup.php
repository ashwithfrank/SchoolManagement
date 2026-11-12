<?php
use App\Core\Http;
use App\Services\{AcademicService, StudentService};

$y = AcademicService::currentYear();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($y ? StudentService::quickSearch(Http::strParam('q'), (int) $y['id']) : [], JSON_UNESCAPED_UNICODE);
exit;
