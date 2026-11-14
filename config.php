<?php
declare(strict_types=1);

/*
 * تنها فایل تنظیمات سایت.
 * این فایل را در File Manager هاست باز کن و فقط مقادیر دیتابیس و مدیر را تغییر بده.
 * اولین بازدید /admin جدول‌ها، محتوا و مدیر اولیه را خودکار می‌سازد.
 */
return [
    'app' => [
        'name' => 'Milad Site',
        'base_url' => '',       // خالی = تشخیص خودکار دامنه
        'base_path' => '',      // خالی = تشخیص خودکار مسیر نصب، مثل /site
        'timezone' => 'Europe/Berlin',
        'debug' => false,       // فقط موقتاً برای عیب‌یابی روشن کن
        'session_name' => 'milad_admin',
    ],

    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'zenorali_milad',
        'username' => 'zenorali_milad',
        'password' => 'miladmilad',
        'charset' => 'utf8mb4',
    ],

    'admin' => [
        'username' => 'milad',
        'display_name' => 'Milad',
        'password' => 'm09146015515@milad',
    ],
];
