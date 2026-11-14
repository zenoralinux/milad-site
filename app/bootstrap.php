<?php
declare(strict_types=1);

define('MILAD_ROOT', dirname(__DIR__));

function app_config(): array
{
    static $config = null;
    if ($config !== null) return $config;

    $defaults = [
        'app' => [
            'name' => 'Milad Site',
            'base_url' => '',
            'base_path' => '',
            'timezone' => 'Europe/Berlin',
            'debug' => false,
            'session_name' => 'milad_admin',
            'ip_hash_key' => '',
        ],
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'zenorali_milad',
            'username' => 'zenorali_milad',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
    ];

    $configuredPath = getenv('MILAD_SITE_CONFIG');
    $configPath = $configuredPath !== false && $configuredPath !== ''
        ? $configuredPath
        : (is_file(MILAD_ROOT . '/config.php')
            ? MILAD_ROOT . '/config.php'
            : MILAD_ROOT . '/config.local.php');

    $loaded = [];
    if (is_file($configPath)) {
        $value = require $configPath;
        if (!is_array($value)) {
            throw new RuntimeException('The PHP configuration file must return an array.');
        }
        $loaded = $value;
    }

    $config = array_replace_recursive($defaults, $loaded);

    $environmentMap = [
        'app.base_url' => 'MILAD_APP_BASE_URL',
        'app.base_path' => 'MILAD_APP_BASE_PATH',
        'app.timezone' => 'MILAD_APP_TIMEZONE',
        'app.ip_hash_key' => 'MILAD_APP_IP_HASH_KEY',
        'database.host' => 'MILAD_DB_HOST',
        'database.port' => 'MILAD_DB_PORT',
        'database.name' => 'MILAD_DB_NAME',
        'database.username' => 'MILAD_DB_USER',
        'database.password' => 'MILAD_DB_PASSWORD',
    ];
    foreach ($environmentMap as $key => $variable) {
        $value = getenv($variable);
        if ($value === false) continue;
        $parts = explode('.', $key);
        $target = &$config;
        foreach ($parts as $part) $target = &$target[$part];
        $target = $key === 'database.port' ? (int) $value : $value;
        unset($target);
    }

    $debug = getenv('MILAD_APP_DEBUG');
    if ($debug !== false) {
        $config['app']['debug'] = filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }

    return $config;
}

function cfg(string $key, mixed $default = null): mixed
{
    $value = app_config();
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

/**
 * Return the URL mount point of this installation.
 * It is detected from the executing PHP script, so /, /portfolio and
 * deeper subdirectory installations use the same application code.
 */
function app_base_path(): string
{
    static $basePath = null;
    if ($basePath !== null) return $basePath;

    if (PHP_SAPI === 'cli' && empty($_SERVER['MILAD_FORCE_BASE_PATH'])) {
        $basePath = '';
        return $basePath;
    }

    $configured = trim((string) cfg('app.base_path', ''));
    if ($configured !== '') {
        $path = '/' . trim(str_replace('\\', '/', $configured), '/');
        $basePath = $path === '/' ? '' : $path;
        return $basePath;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    // SCRIPT_NAME may expose either the physical index.php path or the clean /admin URL.
    $script = preg_replace('#/(?:admin(?:/index)?|api/admin)(?:\.php)?/?$#i', '', $script) ?? $script;
    $script = preg_replace('#/index\.php$#i', '', $script) ?? $script;
    $script = '/' . trim($script, '/');
    $basePath = $script === '/' ? '' : $script;
    return $basePath;
}

/** Build an installation-relative URL without assuming that the app lives at /. */
function app_url(string $path = ''): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') return app_base_path() . '/';
    if ($path[0] === '#' || $path[0] === '?') return $path;
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)) return $path;
    if (str_starts_with($path, '//')) return $path;
    return app_base_path() . '/' . ltrim($path, '/');
}

function app_absolute_url(string $path = ''): string
{
    $origin = trim((string) cfg('app.base_url', ''));
    if ($origin === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'miladalizadw.ir');
        if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i', $host)) {
            $host = 'miladalizadw.ir';
        }
        $origin = ($https ? 'https://' : 'http://') . $host;
    }
    $base = rtrim($origin, '/') . app_base_path();
    return $path === '' || $path === '/'
        ? $base . '/'
        : $base . '/' . ltrim($path, '/');
}

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int {
        return preg_match_all('/./us', $value, $matches) ?: 0;
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string {
        preg_match_all('/./us', $value, $matches);
        $chars = $matches[0] ?? [];
        return implode('', array_slice($chars, $start, $length));
    }
}

date_default_timezone_set((string) cfg('app.timezone', 'UTC'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $database = cfg('database', []);
    foreach (['host', 'name', 'username', 'password'] as $key) {
        if (!isset($database[$key]) || ($key !== 'password' && trim((string) $database[$key]) === '')) {
            throw new RuntimeException('Database configuration is incomplete. Set the database values in config.php/config.local.php or environment variables.');
        }
    }

    $charset = preg_replace('/[^a-z0-9_]/i', '', (string) ($database['charset'] ?? 'utf8mb4'));
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $database['host'],
        (int) ($database['port'] ?? 3306),
        $database['name'],
        $charset
    );

    $pdo = new PDO($dsn, (string) $database['username'], (string) $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name((string) cfg('app.session_name', 'milad_admin'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => app_base_path() !== '' ? app_base_path() . '/' : '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

function csrf_token(): string
{
    start_admin_session();
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    start_admin_session();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!is_string($provided) || !hash_equals((string) ($_SESSION['_csrf'] ?? ''), $provided)) {
        json_response(['ok' => false, 'error' => ['code' => 'CSRF_INVALID', 'message' => 'درخواست معتبر نیست. صفحه را تازه‌سازی کنید.']], 419);
    }
}

function current_admin(): ?array
{
    start_admin_session();
    $now = time();
    if (isset($_SESSION['admin_id'])) {
        if (isset($_SESSION['admin_last_activity']) && ($now - (int) $_SESSION['admin_last_activity']) > 1800) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            return null;
        }
        if (!isset($_SESSION['admin_last_regeneration']) || ($now - (int) $_SESSION['admin_last_regeneration']) > 900) {
            session_regenerate_id(true);
            $_SESSION['admin_last_regeneration'] = $now;
        }
        $_SESSION['admin_last_activity'] = $now;
    }
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id < 1) return null;
    $stmt = db()->prepare('SELECT id, username, display_name, role FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    $admin = $stmt->fetch();
    if (!$admin) {
        unset($_SESSION['admin_id']);
        return null;
    }
    return $admin;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) json_response(['ok' => false, 'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'برای ادامه وارد پنل شوید.']], 401);
    return $admin;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) json_response(['ok' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'داده ارسالی معتبر نیست.']], 400);
    return $data;
}

function request_ip_hash(): string
{
    $secret = (string) cfg('app.ip_hash_key', 'change-this-secret');
    return hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $secret);
}

function log_activity(?int $adminId, string $action, string $entityType = '', ?int $entityId = null, string $summary = ''): void
{
    try {
        $stmt = db()->prepare('INSERT INTO admin_activity_logs (admin_user_id, action, entity_type, entity_id, summary, ip_hash) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$adminId, $action, $entityType, $entityId, mb_substr($summary, 0, 255), request_ip_hash()]);
    } catch (Throwable $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}

function get_site_settings(): array
{
    $rows = db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
    $settings = [];
    foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
    return $settings;
}

function get_site_sections(): array
{
    $rows = db()->query('SELECT section_key, content_json, is_visible, sort_order FROM site_sections ORDER BY sort_order ASC')->fetchAll();
    $sections = [];
    foreach ($rows as $row) {
        $decoded = json_decode((string) $row['content_json'], true);
        $sections[$row['section_key']] = [
            'content' => is_array($decoded) ? $decoded : [],
            'is_visible' => (bool) $row['is_visible'],
            'sort_order' => (int) $row['sort_order'],
        ];
    }
    return $sections;
}

function get_projects(bool $publishedOnly = true): array
{
    $sql = 'SELECT * FROM projects ' . ($publishedOnly ? 'WHERE is_published = 1 ' : '') . 'ORDER BY sort_order ASC, id ASC';
    $projects = db()->query($sql)->fetchAll();
    if (!$projects) return [];
    $ids = array_map(static fn($project) => (int) $project['id'], $projects);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $linkStatement = db()->prepare("SELECT project_id, label, url FROM project_links WHERE project_id IN ($placeholders) ORDER BY sort_order, id");
    $linkStatement->execute($ids);
    $technologyStatement = db()->prepare("SELECT project_id, name FROM project_technologies WHERE project_id IN ($placeholders) ORDER BY sort_order, id");
    $technologyStatement->execute($ids);

    $links = [];
    foreach ($linkStatement->fetchAll() as $row) {
        $links[(int) $row['project_id']][] = ['label' => $row['label'], 'url' => $row['url']];
    }
    $technologies = [];
    foreach ($technologyStatement->fetchAll() as $row) {
        $technologies[(int) $row['project_id']][] = $row['name'];
    }

    foreach ($projects as &$project) {
        $id = (int) $project['id'];
        $project['links'] = $links[$id] ?? [];
        $project['technologies'] = $technologies[$id] ?? [];
        $project['is_featured'] = (bool) $project['is_featured'];
        $project['is_published'] = (bool) $project['is_published'];
    }
    unset($project);
    return $projects;
}

function project_visual_markup(string $variant): string
{
    return match ($variant) {
        'zenora' => '<div class="zenora-symbol">Z<span>.</span></div><span class="visual-caption">A Linux experience, reimagined.</span><span class="visual-orbit"></span>',
        'chat' => '<div class="chat-window"><div class="chat-window-head"><i></i><i></i><i></i><span>ZenoraChat</span></div><div class="chat-bubble chat-bubble--a"></div><div class="chat-bubble chat-bubble--b"></div><div class="chat-bubble chat-bubble--a short"></div><div class="chat-compose"><span></span><b>↗</b></div></div><span class="visual-caption">Conversations, thoughtfully designed.</span>',
        'android' => '<div class="android-device"><div class="android-screen"><span class="android-logo">Z</span><span class="android-line"></span><span class="android-line small"></span><span class="android-card"></span></div></div><span class="visual-caption">Native, focused, familiar.</span>',
        'arm' => '<div class="terminal-window"><div class="terminal-head"><span></span><span></span><span></span><b>zenora — terminal</b></div><div class="terminal-code"><em>$</em> zenora --start<br><i>»</i> initializing environment<br><i>»</i> ready<span class="terminal-caret"></span></div></div><span class="visual-caption">Linux tools, on the move.</span>',
        'wsl' => '<div class="wsl-mark"><span>W</span><b>+</b><span>Z</span></div><div class="wsl-lines"><i></i><i></i><i></i></div><span class="visual-caption">A familiar Linux workflow on Windows.</span>',
        default => '<div class="generic-project-mark">M<span>.</span></div><span class="visual-caption">Designed and built with intent.</span>',
    };
}
