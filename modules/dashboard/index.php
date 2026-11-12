<?php
use App\Core\View;
use App\Services\DashboardService;

View::page('dashboard/index', ['d' => DashboardService::data()], 'Dashboard');
