<?php
declare(strict_types=1);

$host = getenv('MILAD_TEST_DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MILAD_TEST_DB_PORT') ?: 3306);
$name = getenv('MILAD_TEST_DB_NAME') ?: '';
$user = getenv('MILAD_TEST_DB_USER') ?: '';
$password = getenv('MILAD_TEST_DB_PASSWORD') ?: '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "Test database environment variables are missing.\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($sql === false) {
    fwrite(STDERR, "Unable to read database/schema.sql.\n");
    exit(1);
}
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $pdo->exec($statement);
}

$expected = ['users','roles','permissions','role_permissions','user_roles','pages','page_revisions','media','projects','settings','activity_logs','schema_migrations'];
$actual = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missing = array_values(array_diff($expected, $actual));
if ($missing) {
    fwrite(STDERR, 'Missing schema tables: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$pdo->exec("INSERT INTO roles(slug,name) VALUES('ci-owner','CI Owner')");
$roleId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO permissions(slug,description) VALUES('ci.test','CI Test Permission')");
$permissionId = (int)$pdo->lastInsertId();
$assign = $pdo->prepare('INSERT INTO role_permissions(role_id,permission_id) VALUES(:r,:p)');
$assign->execute(['r' => $roleId, 'p' => $permissionId]);

echo 'MySQL schema test passed: ' . count($expected) . " tables created and foreign-key relations exercised.\n";
