<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this installer from the command line only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

if (!cfg('database.host') || !cfg('database.name') || !cfg('database.username')) {
    fwrite(STDERR, "Database configuration missing. Copy config.example.php to config.php or set MILAD_DB_HOST, MILAD_DB_NAME, MILAD_DB_USER and MILAD_DB_PASSWORD.\n");
    exit(1);
}
$ipHashKey = (string) cfg('app.ip_hash_key', '');
if (strlen($ipHashKey) < 32 || str_contains($ipHashKey, 'REPLACE-WITH') || str_contains($ipHashKey, 'SET_A_LONG_RANDOM')) {
    fwrite(STDERR, "Security configuration error: set app.ip_hash_key (or MILAD_APP_IP_HASH_KEY) to a unique random secret of at least 32 characters before installation.\n");
    exit(1);
}

try {
    $pdo = db();
    $schema = file_get_contents(MILAD_ROOT . '/database/schema.sql');
    if ($schema === false) throw new RuntimeException('Could not read database/schema.sql.');
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        $pdo->exec($statement);
    }

    $settings = [
        'site_title' => 'میلاد | توسعه‌دهنده مستقل و سازنده محصولات دیجیتال',
        'site_description' => 'فضای دیجیتال میلاد؛ توسعه‌دهنده مستقل، علاقه‌مند به نرم‌افزار متن‌باز و سازنده پروژه‌هایی مانند Zenora Linux و ZenoraChat.',
        'canonical_url' => rtrim((string) cfg('app.base_url', 'https://miladalizadw.ir'), '/') . '/',
        'og_image_url' => rtrim((string) cfg('app.base_url', 'https://miladalizadw.ir'), '/') . '/og-image.svg',
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
    $sectionStmt = $pdo->prepare('INSERT IGNORE INTO site_sections (section_key, content_json, is_visible, sort_order) VALUES (?, ?, 1, ?)');
    $order = 0;
    foreach ($sections as $key => $content) {
        $sectionStmt->execute([$key, json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $order++]);
    }

    $projectCount = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    if ($projectCount === 0) {
        $projects = [
            [
                'slug' => 'zenora-linux', 'title' => 'Zenora Linux', 'category' => 'os', 'kind_label' => 'سیستم‌عامل · Linux',
                'excerpt' => 'توزیع لینوکسی مبتنی بر Arch با تمرکز بر تجربه کاربری، ابزارهای توسعه و شخصی‌سازی.',
                'description' => 'توزیع لینوکسی مبتنی بر Arch با تمرکز بر تجربه کاربری، ابزارهای توسعه و شخصی‌سازی.',
                'status' => 'active', 'visual_variant' => 'zenora', 'featured' => 1, 'sort' => 1,
                'technologies' => ['Arch Linux', 'Desktop', 'Open Source'],
                'links' => [['label' => 'وب‌سایت پروژه', 'url' => 'https://zenoralinux.ir'], ['label' => 'GitHub', 'url' => 'https://github.com/zenoralinux/zenoralinux']],
            ],
            [
                'slug' => 'zenorachat-web', 'title' => 'ZenoraChat Web', 'category' => 'apps', 'kind_label' => 'اپلیکیشن · Web',
                'excerpt' => 'پیام‌رسان تحت وب با رابط کاربری اختصاصی و تمرکز بر تجربه ارتباطی روان.',
                'description' => 'پیام‌رسان تحت وب با رابط کاربری اختصاصی و تمرکز بر تجربه ارتباطی روان.',
                'status' => 'active', 'visual_variant' => 'chat', 'featured' => 0, 'sort' => 2,
                'technologies' => ['Web App', 'Messaging', 'UI/UX'],
                'links' => [['label' => 'ورود به پیام‌رسان', 'url' => '/chat'], ['label' => 'GitHub', 'url' => 'https://github.com/zenoralinux/zenora_chat']],
            ],
            [
                'slug' => 'zenorachat-android', 'title' => 'ZenoraChat Android', 'category' => 'apps', 'kind_label' => 'اپلیکیشن · Android',
                'excerpt' => 'اپلیکیشن بومی اندروید برای گسترش تجربه ZenoraChat روی دستگاه‌های همراه.',
                'description' => 'اپلیکیشن بومی اندروید برای گسترش تجربه ZenoraChat روی دستگاه‌های همراه.',
                'status' => 'in_progress', 'visual_variant' => 'android', 'featured' => 0, 'sort' => 3,
                'technologies' => ['Android', 'Mobile', 'Native'],
                'links' => [['label' => 'مخزن پروژه', 'url' => 'https://github.com/zenoralinux/zenorachat-android']],
            ],
            [
                'slug' => 'zenora-android', 'title' => 'Zenora Android', 'category' => 'tools', 'kind_label' => 'محیط توسعه · Android',
                'excerpt' => 'محیط لینوکسی مبتنی بر ARM64 برای اجرا در فضای اندروید و ابزارهای خط فرمان.',
                'description' => 'محیط لینوکسی مبتنی بر ARM64 برای اجرا در فضای اندروید و ابزارهای خط فرمان.',
                'status' => 'project', 'visual_variant' => 'arm', 'featured' => 0, 'sort' => 4,
                'technologies' => ['ARM64', 'Linux', 'Proot'],
                'links' => [['label' => 'مخزن پروژه', 'url' => 'https://github.com/zenoralinux/zenora-android']],
            ],
            [
                'slug' => 'zenora-wsl', 'title' => 'Zenora WSL', 'category' => 'os', 'kind_label' => 'محیط توسعه · Windows',
                'excerpt' => 'محیط Zenora مبتنی بر Arch برای Windows Subsystem for Linux.',
                'description' => 'محیط Zenora مبتنی بر Arch برای Windows Subsystem for Linux.',
                'status' => 'project', 'visual_variant' => 'wsl', 'featured' => 0, 'sort' => 5,
                'technologies' => ['WSL', 'Windows', 'Arch Linux'],
                'links' => [['label' => 'مخزن پروژه', 'url' => 'https://github.com/zenoralinux/wsl-zenora-rootfs']],
            ],
        ];
        $projectStmt = $pdo->prepare('INSERT INTO projects (slug, title, category, kind_label, excerpt, description, status, visual_variant, is_featured, is_published, sort_order, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, UTC_TIMESTAMP())');
        $linkStmt = $pdo->prepare('INSERT INTO project_links (project_id, label, url, sort_order) VALUES (?, ?, ?, ?)');
        $techStmt = $pdo->prepare('INSERT INTO project_technologies (project_id, name, sort_order) VALUES (?, ?, ?)');
        foreach ($projects as $project) {
            $projectStmt->execute([$project['slug'], $project['title'], $project['category'], $project['kind_label'], $project['excerpt'], $project['description'], $project['status'], $project['visual_variant'], $project['featured'], $project['sort']]);
            $id = (int) $pdo->lastInsertId();
            foreach ($project['links'] as $i => $link) $linkStmt->execute([$id, $link['label'], $link['url'], $i]);
            foreach ($project['technologies'] as $i => $tech) $techStmt->execute([$id, $tech, $i]);
        }
    }

    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($adminCount === 0) {
        $readInput = static function (string $prompt): string {
            echo $prompt;
            return trim((string) fgets(STDIN));
        };
        $username = trim((string) (getenv('MILAD_ADMIN_USERNAME') ?: $readInput('Create admin username: ')));
        $displayName = trim((string) (getenv('MILAD_ADMIN_DISPLAY_NAME') ?: $readInput('Display name [Milad]: ')));
        $displayName = $displayName !== '' ? $displayName : 'Milad';
        $password = (string) (getenv('MILAD_ADMIN_PASSWORD') ?: '');
        if ($password === '') {
            echo "Create admin password (minimum 12 characters): ";
            $hiddenInput = DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec');
            if ($hiddenInput) shell_exec('stty -echo');
            $password = trim((string) fgets(STDIN));
            if ($hiddenInput) { shell_exec('stty echo'); echo PHP_EOL; }
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $username)) throw new RuntimeException('Username must be 3-80 characters (letters, numbers, dot, dash, underscore).');
        if (strlen($password) < 12) throw new RuntimeException('Password must contain at least 12 characters.');
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, display_name, role) VALUES (?, ?, ?, 'owner')");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName]);
        echo "Admin account created.\n";
    } else {
        echo "An admin account already exists; no new account was created.\n";
    }

    echo "\nInstallation completed.\n";
    echo "Admin URL: " . rtrim((string) cfg('app.base_url', ''), '/') . "/admin\n";
    echo "The public page will use database content when served through PHP.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Installation failed: " . $e->getMessage() . "\n");
    exit(1);
}
