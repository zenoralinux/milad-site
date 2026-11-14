<?php
declare(strict_types=1);

/*
 * Public-safe configuration template.
 * Put real hosting credentials in config.local.php (never commit that file).
 * This file is kept in the repository only as a setup example.
 */
return [
    'app' => [
        'name' => 'Milad Site',
        'base_url' => '',
        'base_path' => '',
        'timezone' => 'Europe/Berlin',
        'debug' => false,
        'session_name' => 'milad_admin',
    ],

    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'YOUR_DATABASE_NAME',
        'username' => 'YOUR_DATABASE_USER',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    'admin' => [
        'username' => 'admin',
        'display_name' => 'Milad',
        'password' => '',
    ],
];
