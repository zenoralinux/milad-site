<?php
declare(strict_types=1);

/**
 * CLI smoke test for root and subdirectory URL generation.
 * Usage: php tests/path-routing.php /portfolio/admin/index.php /portfolio
 */
$scriptName = $argv[1] ?? '/index.php';
$expectedBase = $argv[2] ?? '';
$_SERVER['SCRIPT_NAME'] = $scriptName;
$_SERVER['MILAD_FORCE_BASE_PATH'] = true;
$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['HTTPS'] = 'on';

require dirname(__DIR__) . '/app/bootstrap.php';

$checks = [
    'base path' => app_base_path() === $expectedBase,
    'admin URL' => app_url('admin') === $expectedBase . '/admin',
    'studio URL' => app_url('admin/studio.php') === $expectedBase . '/admin/studio.php',
    'API URL' => app_url('api/admin.php') === $expectedBase . '/api/admin.php',
    'absolute homepage URL' => app_absolute_url('/') === 'https://example.test' . $expectedBase . '/',
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}; expected base path '{$expectedBase}', got '" . app_base_path() . "'.\n");
        exit(1);
    }
}

echo "PASS: {$scriptName} => " . ($expectedBase !== '' ? $expectedBase : '/') . PHP_EOL;
