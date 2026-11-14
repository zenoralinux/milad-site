<?php
/**
 * Browser-based first-run installation for shared hosting.
 * Called automatically by db(); never exposes a public setup endpoint.
 */
function ensure_app_installed(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;

    // Normal requests only need this cheap marker lookup; acquire the advisory lock
    // exclusively while the one-time schema/seed installation is still pending.
    $GLOBALS['milad_setup_stage'] = 'بررسی نصب قبلی';
    try {
        $installed = $pdo->query('SELECT schema_version FROM app_installation WHERE id = 1')->fetchColumn();
        if ($installed !== false) {
            $ready = true;
            return;
        }
    } catch (PDOException $ignored) {
        // First run: app_installation does not exist yet.
    }

    $GLOBALS['milad_setup_stage'] = 'قفل‌گذاری نصب اولیه';
    $lockName = 'milad_site_setup_' . substr(hash('sha256', (string) cfg('database.name', 'site')), 0, 24);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('راه‌اندازی اولیه هم‌زمان در حال اجراست. چند لحظه بعد صفحه را تازه کن.');
    }

    try {
        try {
            $installed = $pdo->query('SELECT schema_version FROM app_installation WHERE id = 1')->fetchColumn();
            if ($installed !== false) {
                $ready = true;
                return;
            }
        } catch (PDOException $ignored) {
            // First request: installation marker table does not exist yet.
        }

        $GLOBALS['milad_setup_stage'] = 'خواندن ساختار دیتابیس';
        $schemaPath = MILAD_ROOT . '/database/schema.sql';
        $schema = is_file($schemaPath) ? file_get_contents($schemaPath) : false;
        if ($schema === false) {
            throw new RuntimeException('فایل database/schema.sql پیدا نشد. همه فایل‌های پروژه را آپلود کن.');
        }
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $tableName = 'جدول دیتابیس';
            if (preg_match('/^CREATE\\s+TABLE\\s+IF\\s+NOT\\s+EXISTS\\s+([a-z0-9_]+)/i', $statement, $tableMatch)) {
                $tableName = $tableMatch[1];
            }
            $GLOBALS['milad_setup_stage'] = 'ساخت ' . $tableName;
            $pdo->exec($statement);
        }

        $GLOBALS['milad_setup_stage'] = 'بررسی تنظیمات حساب مدیر';
        $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($adminCount === 0) {
            $username = trim((string) cfg('admin.username', ''));
            $password = (string) cfg('admin.password', '');
            $displayName = trim((string) cfg('admin.display_name', 'Milad'));

            if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $username)) {
                throw new RuntimeException('مقدار admin.username در config.php معتبر نیست؛ حداقل ۳ نویسه انگلیسی وارد کن.');
            }
            if (strlen($password) < 12 || stripos($password, 'CHANGE-THIS') !== false || stripos($password, 'SET-YOUR') !== false) {
                throw new RuntimeException('در config.php برای admin.password یک رمز اختصاصی حداقل ۱۲ کاراکتری تعیین کن.');
            }
            if ($displayName === '') $displayName = $username;
        }

        $GLOBALS['milad_setup_stage'] = 'ثبت تنظیمات اولیه سایت';
        $pdo->beginTransaction();
        try {
            $settings = [
                'site_title' => 'میلاد | توسعه‌دهنده مستقل و سازنده محصولات دیجیتال',
                'site_description' => 'فضای دیجیتال میلاد؛ توسعه‌دهنده مستقل، علاقه‌مند به نرم‌افزار متن‌باز و سازنده پروژه‌هایی مانند Zenora Linux و ZenoraChat.',
                'canonical_url' => app_absolute_url('/'),
                'og_image_url' => app_absolute_url('/og-image.svg'),
                'github_url' => 'https://github.com/miladalizadw',
                'telegram_url' => 'https://t.me/miladalizadw',
                'avatar_path' => './milad_avatar.jpg',
                'theme_color' => '#080b12',
            ];
            $settingStmt = $pdo->prepare('INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
            foreach ($settings as $key => $value) $settingStmt->execute([$key, $value]);

            $sections = [
                'hero' => ['eyebrow' => 'توسعه‌دهنده مستقل · سازنده محصولات دیجیتال', 'title_line1' => 'ایده‌ها را', 'title_line2' => 'به تجربه تبدیل می‌کنم.', 'description' => 'من میلادم؛ به ساختن نرم‌افزار، تجربه‌کردن فناوری‌های تازه و حل مسئله علاقه دارم. اینجا گوشه‌ای از پروژه‌ها، مسیر فکری و چیزهایی را می‌بینی که با علاقه می‌سازم.', 'primary_cta_text' => 'دیدن پروژه‌ها', 'primary_cta_url' => '#work', 'secondary_cta_text' => 'ارتباط با من', 'secondary_cta_url' => '#contact', 'footnote' => 'ساختن مستقل، یادگیری مداوم، بهبود بی‌وقفه'],
                'intro' => ['text_before' => 'تمرکز من روی ساخت محصولاتی است که', 'text_emphasis' => 'واقعاً به کار می‌آیند.'],
                'projects' => ['heading_before' => 'چیزهایی که', 'heading_emphasis' => 'می‌سازم.', 'description' => 'مجموعه‌ای از پروژه‌ها و تجربه‌هایی که بخشی از مسیر من در توسعه نرم‌افزار هستند.'],
                'about' => ['section_index' => '02 — A LITTLE ABOUT ME', 'title_line1' => 'پشت هر محصول،', 'title_line2' => 'یک طرز فکر است.', 'description' => 'برای من توسعه نرم‌افزار فقط کنار هم گذاشتن کدها نیست؛ فرایند فهم مسئله، طراحی راه‌حل و بهتر کردن تجربه آدم‌هاست.', 'link_text' => 'بیشتر در گیت‌هاب'],
                'principles' => ['items' => [
                    ['number' => '01', 'title' => 'از ایده تا اجرا', 'description' => 'ایده وقتی ارزشمند می‌شود که به چیزی قابل استفاده تبدیل شود.'],
                    ['number' => '02', 'title' => 'سادگی هدفمند', 'description' => 'جزئیات باید به تجربه کمک کنند؛ نه اینکه جلوی آن را بگیرند.'],
                    ['number' => '03', 'title' => 'یادگیری و اشتراک', 'description' => 'ساختن، آزمودن و به اشتراک گذاشتن بخشی از مسیر رشد است.'],
                ]],
                'now' => ['section_index' => '03 — NOW / CURRENT FOCUS', 'title_line1' => 'همیشه چیزی', 'title_line2' => 'در حال ساخته‌شدن است.', 'description' => 'این روزها تمرکزم روی توسعه و بهبود پروژه‌های Zenora، به‌خصوص تجربه ZenoraChat و ابزارهای مبتنی بر لینوکس است. این بخش تصویری از مسیر فعلی من است، نه یک فهرست ثابت از دستاوردها.', 'status_title' => 'در حال ساختن', 'status_subtitle' => 'Build · Learn · Improve'],
                'contact' => ['section_index' => '04 — LET’S CONNECT', 'title_line1' => 'ایده‌ای داری؟', 'title_line2' => 'گفت‌وگو کنیم.', 'description' => 'برای دیدن کدها، دنبال‌کردن پروژه‌ها یا شروع یک گفت‌وگوی فنی، از این مسیرها در دسترسم.'],
                'footer' => ['tagline' => 'ساخته‌شده با دقت و علاقه.', 'copyright' => 'تمام حقوق محفوظ است.'],
            ];
            $GLOBALS['milad_setup_stage'] = 'ثبت بخش‌های اولیه سایت';
            $sectionStmt = $pdo->prepare('INSERT IGNORE INTO site_sections (section_key, content_json, is_visible, sort_order) VALUES (?, ?, 1, ?)');
            $order = 0;
            foreach ($sections as $key => $content) {
                $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded === false) throw new RuntimeException('ساخت محتوای اولیه سایت ناموفق بود.');
                $sectionStmt->execute([$key, $encoded, $order++]);
            }

            $GLOBALS['milad_setup_stage'] = 'ثبت پروژه‌های اولیه';
            $projectCount = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
            if ($projectCount === 0) {
                $projects = [
                    ['zenora-linux', 'Zenora Linux', 'os', 'سیستم‌عامل · Linux', 'توزیع لینوکسی مبتنی بر Arch با تمرکز بر تجربه کاربری، ابزارهای توسعه و شخصی‌سازی.', 'active', 'zenora', 1, ['Arch Linux', 'Desktop', 'Open Source'], [['وب‌سایت پروژه', 'https://zenoralinux.ir'], ['GitHub', 'https://github.com/zenoralinux/zenoralinux']]],
                    ['zenorachat-web', 'ZenoraChat Web', 'apps', 'اپلیکیشن · Web', 'پیام‌رسان تحت وب با رابط کاربری اختصاصی و تمرکز بر تجربه ارتباطی روان.', 'active', 'chat', 0, ['Web App', 'Messaging', 'UI/UX'], [['ورود به پیام‌رسان', '/chat'], ['GitHub', 'https://github.com/zenoralinux/zenora_chat']]],
                    ['zenorachat-android', 'ZenoraChat Android', 'apps', 'اپلیکیشن · Android', 'اپلیکیشن بومی اندروید برای گسترش تجربه ZenoraChat روی دستگاه‌های همراه.', 'in_progress', 'android', 0, ['Android', 'Mobile', 'Native'], [['مخزن پروژه', 'https://github.com/zenoralinux/zenorachat-android']]],
                    ['zenora-android', 'Zenora Android', 'tools', 'محیط توسعه · Android', 'محیط لینوکسی مبتنی بر ARM64 برای اجرا در فضای اندروید و ابزارهای خط فرمان.', 'project', 'arm', 0, ['ARM64', 'Linux', 'Proot'], [['مخزن پروژه', 'https://github.com/zenoralinux/zenora-android']]],
                    ['zenora-wsl', 'Zenora WSL', 'os', 'محیط توسعه · Windows', 'محیط Zenora مبتنی بر Arch برای Windows Subsystem for Linux.', 'project', 'wsl', 0, ['WSL', 'Windows', 'Arch Linux'], [['مخزن پروژه', 'https://github.com/zenoralinux/wsl-zenora-rootfs']]],
                ];
                $projectStmt = $pdo->prepare('INSERT INTO projects (slug, title, category, kind_label, excerpt, description, status, visual_variant, is_featured, is_published, sort_order, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, UTC_TIMESTAMP())');
                $linkStmt = $pdo->prepare('INSERT INTO project_links (project_id, label, url, sort_order) VALUES (?, ?, ?, ?)');
                $techStmt = $pdo->prepare('INSERT INTO project_technologies (project_id, name, sort_order) VALUES (?, ?, ?)');
                foreach ($projects as $index => $project) {
                    $projectStmt->execute([$project[0], $project[1], $project[2], $project[3], $project[4], $project[4], $project[5], $project[6], $project[7], $index + 1]);
                    $id = (int) $pdo->lastInsertId();
                    foreach ($project[9] as $i => $link) $linkStmt->execute([$id, $link[0], $link[1], $i]);
                    foreach ($project[8] as $i => $technology) $techStmt->execute([$id, $technology, $i]);
                }
            }

            if ($adminCount === 0) {
                $GLOBALS['milad_setup_stage'] = 'ساخت حساب مدیر';
                $adminStmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, display_name, role) VALUES (?, ?, ?, 'owner')");
                $adminStmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName]);
            }

            $GLOBALS['milad_setup_stage'] = 'ثبت پایان نصب';
            $pdo->exec("INSERT INTO app_installation (id, schema_version) VALUES (1, '1') ON DUPLICATE KEY UPDATE schema_version = VALUES(schema_version)");
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $ready = true;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}
