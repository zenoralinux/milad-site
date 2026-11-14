<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

try {
    db()->query('SELECT 1');
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'service' => 'milad-site-cms']);
} catch (Throwable $e) {
    error_log('Health check failed: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'service' => 'milad-site-cms']);
}
