<?php
/**
 * Example configuration. The first-run setup page (public/setup.php) writes the real
 * file app/config/config.php for you. To configure by hand: copy this file to config.php
 * and fill in your own values. config.php is never shipped and must never be committed.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'alphaomega_school',
        'user'    => 'aosms_user',       // create a dedicated MySQL user; avoid root
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone'        => 'Asia/Kolkata',
        'debug'           => false,       // true shows error details; never use on a live system
        'session_name'    => 'AOSMSSESS',
        'idle_minutes'    => 30,
        'max_session_hrs' => 12,
    ],
];
