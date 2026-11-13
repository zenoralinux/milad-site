<?php
declare(strict_types=1);

final class Installer
{
    public static function handle(): void
    {
        if (app_installed()) {
            http_response_code(404);
            render_view('error', ['title' => 'نصب انجام شده است', 'message' => 'نصب‌کننده پس از راه‌اندازی قفل شده است.'], 'none');
            return;
        }

        $requirements = self::requirements();
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            try {
                foreach ($requirements as $item) {
                    if (!$item['ok']) throw new RuntimeException('پیش‌نیازهای محیط کامل نیستند. ابتدا موارد قرمز را برطرف کن.');
                }
                self::install();
                redirect('login?installed=1');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
        render_view('install', ['requirements' => $requirements, 'error' => $error], 'none');
    }

    private static function requirements(): array
    {
        $tokenConfigured = (string)getenv('MILAD_INSTALL_TOKEN') !== '' || is_file(APP_ROOT . '/storage/install-token.hash');
        return [
            ['label' => 'PHP 8.1 یا جدیدتر', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>=')],
            ['label' => 'افزونه PDO و PDO MySQL', 'ok' => extension_loaded('pdo') && extension_loaded('pdo_mysql')],
            ['label' => 'افزونه‌های Fileinfo، DOM، JSON، OpenSSL و mbstring', 'ok' =>
                extension_loaded('fileinfo') && extension_loaded('dom') && extension_loaded('json') &&
                extension_loaded('openssl') && extension_loaded('mbstring')],
            ['label' => 'دسترسی نوشتن config و storage', 'ok' => is_writable(APP_ROOT . '/config') && is_writable(APP_ROOT . '/storage')],
            ['label' => 'توکن نصب یک‌بارمصرف تنظیم شده', 'ok' => $tokenConfigured],
        ];
    }

    private static function verifyInstallToken(): void
    {
        $submitted = trim((string)($_POST['install_token'] ?? ''));
        if ($submitted === '') throw new RuntimeException('توکن نصب را وارد کن.');
        $environmentToken = (string)getenv('MILAD_INSTALL_TOKEN');
        if ($environmentToken !== '') {
            if (!hash_equals($environmentToken, $submitted)) throw new RuntimeException('توکن نصب معتبر نیست.');
            return;
        }
        $hashFile = APP_ROOT . '/storage/install-token.hash';
        if (!is_file($hashFile)) throw new RuntimeException('توکن نصب تنظیم نشده است. دستور ساخت توکن را در راهنمای نصب اجرا کن.');
        $hash = trim((string)file_get_contents($hashFile));
        if ($hash === '' || !password_verify($submitted, $hash)) throw new RuntimeException('توکن نصب معتبر نیست.');
    }

    private static function install(): void
    {
        self::verifyInstallToken();

        $name = trim((string)($_POST['admin_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['admin_email'] ?? '')));
        $password = (string)($_POST['admin_password'] ?? '');
        $confirm = (string)($_POST['admin_password_confirm'] ?? '');
        $title = trim((string)($_POST['site_title'] ?? ''));
        $siteUrl = rtrim(trim((string)($_POST['site_url'] ?? '')), '/');
        $timezone = trim((string)($_POST['timezone'] ?? 'Europe/Berlin'));
        $host = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
        $port = (int)($_POST['db_port'] ?? 3306);
        $database = trim((string)($_POST['db_name'] ?? ''));
        $dbUser = trim((string)($_POST['db_user'] ?? ''));
        $dbPassword = (string)($_POST['db_password'] ?? '');

        if ($name === '' || mb_strlen($name) > 120) throw new RuntimeException('نام مدیر معتبر نیست.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('ایمیل مدیر معتبر نیست.');
        if (strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new RuntimeException('رمز باید حداقل ۱۲ کاراکتر و شامل حروف و عدد باشد.');
        }
        if (!hash_equals($password, $confirm)) throw new RuntimeException('تکرار رمز عبور مطابقت ندارد.');
        if ($title === '' || !filter_var($siteUrl, FILTER_VALIDATE_URL) ||
            !in_array(parse_url($siteUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('عنوان یا URL سایت معتبر نیست.');
        }
        if ($host === '' || !preg_match('/^[A-Za-z0-9.:-]+$/', $host) || $database === '' || $dbUser === '' ||
            $port < 1 || $port > 65535 || !preg_match('/^[a-zA-Z0-9_$-]+$/', $database)) {
            throw new RuntimeException('اطلاعات دیتابیس معتبر یا کامل نیست.');
        }
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) throw new RuntimeException('منطقه زمانی معتبر نیست.');

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $dbUser,
            $dbPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );

        $schema = file_get_contents(APP_ROOT . '/database/schema.sql');
        if ($schema === false) throw new RuntimeException('فایل schema.sql پیدا نشد.');
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) $pdo->exec($statement);

        $pdo->beginTransaction();
        try {
            $pdo->exec("INSERT IGNORE INTO roles(slug,name) VALUES('owner','مالک'),('administrator','مدیر'),('editor','ویرایشگر')");
            $permissions = [
                'dashboard.view' => 'مشاهده داشبورد',
                'pages.manage' => 'مدیریت صفحات',
                'projects.manage' => 'مدیریت پروژه‌ها',
                'media.manage' => 'مدیریت رسانه',
                'settings.manage' => 'مدیریت تنظیمات',
                'users.manage' => 'مدیریت کاربران',
            ];
            $insertPermission = $pdo->prepare('INSERT IGNORE INTO permissions(slug,description) VALUES(:slug,:description)');
            foreach ($permissions as $slug => $description) $insertPermission->execute(['slug' => $slug, 'description' => $description]);

            $ownerRoleId = (int)$pdo->query("SELECT id FROM roles WHERE slug='owner'")->fetchColumn();
            $assignPermissions = $pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT :role_id,id FROM permissions');
            $assignPermissions->execute(['role_id' => $ownerRoleId]);

            $insertUser = $pdo->prepare('INSERT INTO users(name,email,password_hash,is_active,created_at) VALUES(:name,:email,:hash,1,NOW())');
            $insertUser->execute(['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:user_id,:role_id)')->execute(['user_id' => $userId, 'role_id' => $ownerRoleId]);

            $defaults = [
                'site_title' => $title,
                'site_tagline' => 'فضای دیجیتال شخصی',
                'hero_title' => 'ایده‌ها را به تجربه تبدیل می‌کنم.',
                'hero_intro' => 'من میلادم؛ به ساختن نرم‌افزار و حل مسئله علاقه دارم.',
                'contact_email' => $email,
                'github_url' => 'https://github.com/miladalizadw',
                'telegram_url' => 'https://t.me/miladalizadw',
            ];
            $insertSetting = $pdo->prepare('INSERT IGNORE INTO settings(setting_key,setting_value,updated_at) VALUES(:key,:value,NOW())');
            foreach ($defaults as $key => $value) $insertSetting->execute(['key' => $key, 'value' => $value]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }

        $config = [
            'app' => ['name' => $title, 'base_url' => $siteUrl, 'timezone' => $timezone, 'key' => bin2hex(random_bytes(32))],
            'database' => ['host' => $host, 'port' => $port, 'name' => $database, 'user' => $dbUser, 'password' => $dbPassword],
        ];
        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        $temporary = APP_ROOT . '/config/local.php.tmp';
        if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, APP_ROOT . '/config/local.php')) {
            @unlink($temporary);
            throw new RuntimeException('ذخیره تنظیمات نصب ناموفق بود.');
        }
        @chmod(APP_ROOT . '/config/local.php', 0640);
        $GLOBALS['app_config'] = $config;
        date_default_timezone_set($timezone);

        if (file_put_contents(APP_ROOT . '/storage/install.lock', 'installed ' . gmdate('c'), LOCK_EX) === false) {
            throw new RuntimeException('ساخت قفل نصب ناموفق بود.');
        }
        @chmod(APP_ROOT . '/storage/install.lock', 0640);
        @unlink(APP_ROOT . '/storage/install-token.hash');
    }
}
