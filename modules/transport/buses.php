<?php
use App\Core\View;
use App\Services\TransportService;

View::page('transport/buses', ['buses' => TransportService::buses()], 'Buses');
