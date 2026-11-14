<?php
declare(strict_types=1);

/* Copy to config.local.php on the host and fill in real values there. */
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
