<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = db();

$expected = [
    'admin_users' => 1,
    'site_sections' => 8,
    'projects' => 5,
    'app_installation' => 1,
];

foreach ($expected as $table => $minimum) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    if ($count < $minimum) {
        fwrite(STDERR, "FAIL: {$table} expected at least {$minimum} records, got {$count}.\n");
        exit(1);
    }
}

// Ensure the first-run installer is safe to call again after installation.
ensure_app_installed($pdo);

$admin = $pdo->query('SELECT username, password_hash FROM admin_users LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$admin || $admin['username'] !== 'ci-admin' || !password_verify('CI-Only-Strong-Password-2026!', $admin['password_hash'])) {
    fwrite(STDERR, "FAIL: initial administrator was not created correctly.\n");
    exit(1);
}

echo "PASS: MySQL schema, seed data, initial admin and repeat installation.\n";
