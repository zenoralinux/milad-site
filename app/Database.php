<?php
declare(strict_types=1);
final class Database
{
    private static ?PDO $connection = null;
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) return self::$connection;
        $file = APP_ROOT . '/config/local.php';
        if (!is_file($file)) throw new RuntimeException('CMS is not installed.');
        $config = require $file;
        $db = $config['database'] ?? [];
        $host = (string)($db['host'] ?? '127.0.0.1');
        $port = (int)($db['port'] ?? 3306);
        $name = (string)($db['name'] ?? '');
        if ($name === '') throw new RuntimeException('Database configuration is incomplete.');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        self::$connection = new PDO($dsn, (string)($db['user'] ?? ''), (string)($db['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$connection;
    }
}
