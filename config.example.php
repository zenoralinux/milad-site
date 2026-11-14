<?php
declare(strict_types=1);

/**
 * Copy this file to config.php and fill in the database credentials.
 * Keep config.php out of Git. Prefer a path outside the web document root
 * and point MILAD_SITE_CONFIG to it in production.
 */
return [
    'app' => [
        'name' => 'Milad Site',
        'base_url' => 'https://miladalizadw.ir',
        'timezone' => 'Europe/Berlin',
        'debug' => false,
        'session_name' => 'milad_admin',
        'ip_hash_key' => 'REPLACE-WITH-A-LONG-RANDOM-SECRET',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'milad_site',
        'username' => 'milad_site_user',
        'password' => 'CHANGE-ME',
        'charset' => 'utf8mb4',
    ],
];
