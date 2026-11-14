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
        'base_url' => '',
        'base_path' => '', // Empty means auto-detect the installation directory.
        'timezone' => 'Europe/Berlin',
        'debug' => false,
        'session_name' => 'milad_admin',
        'ip_hash_key' => 'REPLACE-WITH-A-LONG-RANDOM-SECRET',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'zenorali_milad',
        'username' => 'zenorali_milad',
        'password' => getenv('MILAD_DB_PASSWORD') ?: '', // Keep the password out of Git.
        'charset' => 'utf8mb4',
    ],
];
