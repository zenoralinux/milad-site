<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/page_builder.php';
start_admin_session();

function api_fail(string $code, string $message, int $status = 400, array $fields = []): void
{
    json_response(['ok' => false, 'error' => ['code' => $code, 'message' => $message, 'fields' => (object) $fields]], $status);
}

function validate_url_value(string $url): bool
{
    if ($url === '') return false;
    if (str_starts_with($url, '/')) return !str_starts_with($url, '//');
    if (str_starts_with($url, './') && !str_contains($url, '..')) return true;
    $parts = parse_url($url);
    return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
}

function clean_project_input(array $data): array
{
    $errors = [];
    $title = trim((string) ($data['title'] ?? ''));
    $slug = strtolower(trim((string) ($data['slug'] ?? '')));
    $category = (string) ($data['category'] ?? 'tools');
    $status = (string) ($data['status'] ?? 'active');
    $variant = (string) ($data['visual_variant'] ?? 'generic');
    $kind = trim((string) ($data['kind_label'] ?? ''));
    $excerpt = trim((string) ($data['excerpt'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    if ($title === '' || mb_strlen($title) > 180) $errors['title'] = 'عنوان پروژه الزامی و حداکثر ۱۸۰ نویسه است.';
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 160) $errors['slug'] = 'Slug فقط باید شامل حروف انگلیسی کوچک، عدد و خط تیره باشد.';
    if (!in_array($category, ['os', 'apps', 'tools'], true)) $errors['category'] = 'دسته‌بندی معتبر نیست.';
    if (!in_array($status, ['active', 'in_progress', 'paused', 'project', 'archived'], true)) $errors['status'] = 'وضعیت پروژه معتبر نیست.';
    if (!in_array($variant, ['zenora', 'chat', 'android', 'arm', 'wsl', 'generic'], true)) $errors['visual_variant'] = 'قالب نمایشی معتبر نیست.';
    if (mb_strlen($kind) > 100 || mb_strlen($excerpt) > 500) $errors['excerpt'] = 'برچسب یا توضیحات کوتاه بیش از حد مجاز است.';
    if (mb_strlen($description) > 20000) $errors['description'] = 'توضیحات بیش از حد مجاز است.';
    $technologies = $data['technologies'] ?? [];
    if (!is_array($technologies)) $errors['technologies'] = 'فناوری‌ها باید آرایه باشند.';
    $technologies = array_values(array_unique(array_filter(array_map(static fn($v) => trim((string) $v), is_array($technologies) ? $technologies : []))));
    if (count($technologies) > 20) $errors['technologies'] = 'حداکثر ۲۰ فناوری می‌توان ثبت کرد.';
    foreach ($technologies as $tech) if (mb_strlen($tech) > 80) $errors['technologies'] = 'نام هر فناوری باید حداکثر ۸۰ نویسه باشد.';
    $links = $data['links'] ?? [];
    if (!is_array($links) || count($links) > 12) $errors['links'] = 'حداکثر ۱۲ لینک مجاز است.';
    $normalizedLinks = [];
    foreach (is_array($links) ? $links : [] as $i => $link) {
        if (!is_array($link)) continue;
        $label = trim((string) ($link['label'] ?? ''));
        $url = trim((string) ($link['url'] ?? ''));
        if ($label === '' && $url === '') continue;
        if ($label === '' || mb_strlen($label) > 100 || !validate_url_value($url)) {
            $errors['links'] = 'عنوان یا آدرس یکی از لینک‌ها معتبر نیست؛ فقط HTTPS یا مسیر داخلی مجاز است.';
            continue;
        }
        $normalizedLinks[] = ['label' => $label, 'url' => $url];
    }
    if ($errors) api_fail('VALIDATION_ERROR', 'اطلاعات پروژه را بررسی کنید.', 422, $errors);
    return [
        'slug' => $slug, 'title' => $title, 'category' => $category,
        'kind_label' => $kind, 'excerpt' => $excerpt, 'description' => $description,
        'status' => $status, 'visual_variant' => $variant,
        'is_featured' => !empty($data['is_featured']) ? 1 : 0,
        'is_published' => !empty($data['is_published']) ? 1 : 0,
        'sort_order' => max(0, min(100000, (int) ($data['sort_order'] ?? 0))),
        'technologies' => $technologies, 'links' => $normalizedLinks,
    ];
}

function save_project(PDO $pdo, array $project, ?int $id): int
{
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $stmt = $pdo->prepare('INSERT INTO projects (slug,title,category,kind_label,excerpt,description,status,visual_variant,is_featured,is_published,sort_order,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,IF(?,UTC_TIMESTAMP(),NULL))');
            $stmt->execute([$project['slug'],$project['title'],$project['category'],$project['kind_label'],$project['excerpt'],$project['description'],$project['status'],$project['visual_variant'],$project['is_featured'],$project['is_published'],$project['sort_order'],$project['is_published']]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare('UPDATE projects SET slug=?,title=?,category=?,kind_label=?,excerpt=?,description=?,status=?,visual_variant=?,is_featured=?,is_published=?,sort_order=?,published_at=IF(?=1,COALESCE(published_at,UTC_TIMESTAMP()),NULL) WHERE id=?');
            $stmt->execute([$project['slug'],$project['title'],$project['category'],$project['kind_label'],$project['excerpt'],$project['description'],$project['status'],$project['visual_variant'],$project['is_featured'],$project['is_published'],$project['sort_order'],$project['is_published'],$id]);
            $pdo->prepare('DELETE FROM project_links WHERE project_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM project_technologies WHERE project_id=?')->execute([$id]);
        }
        $linkStmt = $pdo->prepare('INSERT INTO project_links (project_id,label,url,sort_order) VALUES (?,?,?,?)');
        foreach ($project['links'] as $i => $link) $linkStmt->execute([$id,$link['label'],$link['url'],$i]);
        $techStmt = $pdo->prepare('INSERT INTO project_technologies (project_id,name,sort_order) VALUES (?,?,?)');
        foreach ($project['technologies'] as $i => $tech) $techStmt->execute([$id,$tech,$i]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

try {
    $pdo = db();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $action = (string) ($_GET['action'] ?? '');

    if ($action === 'login' && $method === 'POST') {
        verify_csrf();
        $data = json_body();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $identityHash = hash('sha256', strtolower($username));
        $ipHash = request_ip_hash();
        $limit = $pdo->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE identity_hash=? AND ip_hash=? AND was_successful=0 AND attempted_at >= (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)');
        $limit->execute([$identityHash, $ipHash]);
        if ((int) $limit->fetchColumn() >= 5) api_fail('LOGIN_THROTTLED', 'تلاش‌های ورود بیش از حد مجاز بوده است. ۱۵ دقیقه دیگر دوباره امتحان کنید.', 429);
        $stmt = $pdo->prepare('SELECT id,username,password_hash,display_name,role FROM admin_users WHERE username=? AND is_active=1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $ok = $user && password_verify($password, (string) $user['password_hash']);
        $attempt = $pdo->prepare('INSERT INTO admin_login_attempts (identity_hash,ip_hash,was_successful) VALUES (?,?,?)');
        $attempt->execute([$identityHash,$ipHash,$ok ? 1 : 0]);
        $pdo->exec('DELETE FROM admin_login_attempts WHERE attempted_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)');
        if (!$ok) api_fail('INVALID_CREDENTIALS', 'نام کاربری یا رمز عبور درست نیست.', 401);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE admin_users SET last_login_at=UTC_TIMESTAMP() WHERE id=?')->execute([$user['id']]);
        log_activity((int) $user['id'], 'login', 'auth', (int) $user['id'], 'ورود موفق به پنل');
        json_response(['ok' => true, 'data' => ['admin' => ['display_name' => $user['display_name'], 'username' => $user['username']], 'csrf' => $_SESSION['_csrf']], 'message' => 'ورود موفق بود.']);
    }

    if ($action === 'logout' && $method === 'POST') {
        $admin = require_admin();
        verify_csrf();
        log_activity((int) $admin['id'], 'logout', 'auth', (int) $admin['id'], 'خروج از پنل');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        json_response(['ok' => true, 'data' => [], 'message' => 'از پنل خارج شدید.']);
    }

    $admin = require_admin();
    if ($method !== 'GET') verify_csrf();

    switch ($action) {
        case 'session':
            json_response(['ok' => true, 'data' => ['admin' => $admin, 'csrf' => csrf_token()]]);
        case 'dashboard':
            $total = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
            $published = (int) $pdo->query('SELECT COUNT(*) FROM projects WHERE is_published=1')->fetchColumn();
            $drafts = $total - $published;
            $logs = $pdo->query('SELECT action,entity_type,summary,created_at FROM admin_activity_logs ORDER BY id DESC LIMIT 8')->fetchAll();
            json_response(['ok' => true, 'data' => ['total_projects' => $total, 'published_projects' => $published, 'draft_projects' => $drafts, 'activity' => $logs]]);
        case 'sections':
            $rows = $pdo->query('SELECT section_key,content_json,is_visible,sort_order,updated_at FROM site_sections ORDER BY sort_order')->fetchAll();
            $result = [];
            foreach ($rows as $row) {
                $content = json_decode((string) $row['content_json'], true);
                $result[] = ['key' => $row['section_key'], 'content' => is_array($content) ? $content : [], 'is_visible' => (bool) $row['is_visible'], 'sort_order' => (int) $row['sort_order'], 'updated_at' => $row['updated_at']];
            }
            json_response(['ok' => true, 'data' => $result]);
        case 'section-save':
            if ($method !== 'PUT') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $data = json_body();
            $key = (string) ($data['key'] ?? '');
            $allowed = ['hero','intro','projects','about','principles','now','contact','footer'];
            if (!in_array($key, $allowed, true)) api_fail('NOT_FOUND', 'بخش موردنظر پیدا نشد.', 404);
            $content = $data['content'] ?? null;
            if (!is_array($content)) api_fail('VALIDATION_ERROR', 'محتوای بخش معتبر نیست.', 422);
            $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false || strlen($encoded) > 50000) api_fail('VALIDATION_ERROR', 'حجم محتوا بیش از حد مجاز است.', 422);
            foreach ($content as $field => $value) {
                if (!is_string($field) || (!is_string($value) && !is_array($value) && !is_numeric($value))) api_fail('VALIDATION_ERROR', 'ساختار محتوا معتبر نیست.', 422);
                if (is_string($value) && mb_strlen($value) > 5000) api_fail('VALIDATION_ERROR', 'یکی از فیلدها بیش از حد طولانی است.', 422);
            }
            $visible = !empty($data['is_visible']) ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE site_sections SET content_json=?,is_visible=?,updated_by=? WHERE section_key=?');
            $stmt->execute([$encoded,$visible,$admin['id'],$key]);
            log_activity((int) $admin['id'], 'update', 'section', null, 'ویرایش بخش ' . $key);
            json_response(['ok' => true, 'data' => ['key' => $key], 'message' => 'بخش ذخیره شد.']);
        case 'projects':
            if ($method === 'GET') {
                $projects = get_projects(false);
                json_response(['ok' => true, 'data' => $projects]);
            }
            if ($method === 'POST') {
                $project = clean_project_input(json_body());
                $id = save_project($pdo, $project, null);
                log_activity((int) $admin['id'], 'create', 'project', $id, 'ایجاد پروژه ' . $project['title']);
                json_response(['ok' => true, 'data' => ['id' => $id], 'message' => 'پروژه ایجاد شد.'], 201);
            }
            api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
        case 'project-save':
            if ($method !== 'PUT') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $id = (int) ($_GET['id'] ?? 0);
            if ($id < 1) api_fail('INVALID_ID', 'شناسه پروژه معتبر نیست.', 422);
            $exists = $pdo->prepare('SELECT id FROM projects WHERE id=?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) api_fail('NOT_FOUND', 'پروژه پیدا نشد.', 404);
            $project = clean_project_input(json_body());
            save_project($pdo, $project, $id);
            log_activity((int) $admin['id'], 'update', 'project', $id, 'ویرایش پروژه ' . $project['title']);
            json_response(['ok' => true, 'data' => ['id' => $id], 'message' => 'پروژه به‌روزرسانی شد.']);
        case 'project-delete':
            if ($method !== 'DELETE') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $id = (int) ($_GET['id'] ?? 0);
            if ($id < 1) api_fail('INVALID_ID', 'شناسه پروژه معتبر نیست.', 422);
            $stmt = $pdo->prepare('DELETE FROM projects WHERE id=?');
            $stmt->execute([$id]);
            if (!$stmt->rowCount()) api_fail('NOT_FOUND', 'پروژه پیدا نشد.', 404);
            log_activity((int) $admin['id'], 'delete', 'project', $id, 'حذف پروژه');
            json_response(['ok' => true, 'data' => [], 'message' => 'پروژه حذف شد.']);
        case 'project-reorder':
            if ($method !== 'POST') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $ids = json_body()['ids'] ?? [];
            if (!is_array($ids) || count($ids) > 500) api_fail('VALIDATION_ERROR', 'فهرست مرتب‌سازی معتبر نیست.', 422);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE projects SET sort_order=? WHERE id=?');
            foreach (array_values($ids) as $index => $id) $stmt->execute([$index + 1, (int) $id]);
            $pdo->commit();
            log_activity((int) $admin['id'], 'reorder', 'project', null, 'تغییر ترتیب پروژه‌ها');
            json_response(['ok' => true, 'data' => [], 'message' => 'ترتیب پروژه‌ها ذخیره شد.']);
        case 'builder':
            if ($method !== 'GET') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $pageStmt = $pdo->query("SELECT id, slug, title, status FROM pages WHERE slug='home' LIMIT 1");
            $page = $pageStmt->fetch();
            if (!$page) api_fail('BUILDER_NOT_READY', 'صفحه‌ساز هنوز راه‌اندازی نشده است.', 503);
            json_response(['ok' => true, 'data' => [
                'page' => $page,
                'blocks' => builder_home_blocks(),
                'design' => builder_design_settings()
            ]]);

        case 'builder-block-add':
            if ($method !== 'POST') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $data = json_body();
            $type = (string) ($data['type'] ?? '');
            $defaults = [
                'text' => ['label' => 'بخش متنی جدید', 'content' => ['title' => 'عنوان جدید', 'body' => 'متن این بخش را ویرایش کن.']],
                'cta' => ['label' => 'دعوت به اقدام', 'content' => ['title' => 'آماده شروع یک پروژه هستی؟', 'body' => 'برای شروع گفت‌وگو با من در ارتباط باش.', 'button_text' => 'ارتباط با من', 'button_url' => '#contact']],
                'stats' => ['label' => 'آمار و اعداد', 'content' => ['title' => 'چند عدد درباره مسیر من', 'items' => [
                    ['value' => '۰۵+', 'label' => 'پروژه', 'description' => 'ایده‌هایی که ساخته شده‌اند'],
                    ['value' => '۱۰+', 'label' => 'فناوری', 'description' => 'ابزارهای مورد استفاده']
                ]]],
                'spacer' => ['label' => 'فاصله عمودی', 'content' => ['height' => 48]],
                'container' => ['label' => 'کانتینر تو در تو', 'content' => ['direction' => 'column', 'children' => [
                    ['id' => 'node-' . bin2hex(random_bytes(4)), 'type' => 'heading', 'content' => ['text' => 'عنوان داخل کانتینر', 'level' => 2]],
                    ['id' => 'node-' . bin2hex(random_bytes(4)), 'type' => 'paragraph', 'content' => ['text' => 'این یک چیدمان تو در تو است؛ عناصر را از پنل تنظیمات مدیریت کن.']]
                ]]],
                'grid' => ['label' => 'گرید واکنش‌گرا', 'content' => ['columns' => 3, 'children' => [
                    ['id' => 'node-' . bin2hex(random_bytes(4)), 'type' => 'card', 'content' => ['title' => 'کارت اول', 'body' => 'توضیحات کارت']],
                    ['id' => 'node-' . bin2hex(random_bytes(4)), 'type' => 'card', 'content' => ['title' => 'کارت دوم', 'body' => 'توضیحات کارت']],
                    ['id' => 'node-' . bin2hex(random_bytes(4)), 'type' => 'card', 'content' => ['title' => 'کارت سوم', 'body' => 'توضیحات کارت']]
                ]]],
                'heading' => ['label' => 'عنوان', 'content' => ['text' => 'عنوان جدید', 'level' => 2]],
                'paragraph' => ['label' => 'متن', 'content' => ['text' => 'متن خود را اینجا بنویسید.']],
                'button' => ['label' => 'دکمه', 'content' => ['text' => 'شروع کنید', 'url' => '#contact']],
                'image' => ['label' => 'تصویر', 'content' => ['src' => '', 'alt' => '']],
                'card' => ['label' => 'کارت', 'content' => ['title' => 'عنوان کارت', 'body' => 'توضیحات کارت']],
                'divider' => ['label' => 'خط جداکننده', 'content' => []],
                'icon' => ['label' => 'آیکون', 'content' => ['symbol' => '✦']],
                'counter' => ['label' => 'شمارنده', 'content' => ['value' => '۱۲+', 'label' => 'عنوان آمار']],
                'list' => ['label' => 'فهرست', 'content' => ['items' => [['text' => 'مورد اول'], ['text' => 'مورد دوم']]]],
                'video' => ['label' => 'ویدیو', 'content' => ['url' => '', 'title' => 'ویدیو']]
            ];
            if (!isset($defaults[$type])) api_fail('INVALID_BLOCK_TYPE', 'نوع بلوک قابل افزودن نیست.', 422);
            $pageId = builder_home_page_id();
            $maxOrder = (int) $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM page_blocks WHERE page_id=" . $pageId)->fetchColumn();
            $blockKey = 'custom-' . bin2hex(random_bytes(6));
            $insert = $pdo->prepare("INSERT INTO page_blocks (page_id,block_key,block_type,label,content_json,settings_json,is_visible,sort_order) VALUES (?,?,?,?,?,'{}',1,?)");
            $encoded = json_encode($defaults[$type]['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $insert->execute([$pageId,$blockKey,$type,$defaults[$type]['label'],$encoded,$maxOrder + 10]);
            $newId = (int) $pdo->lastInsertId();
            log_activity((int) $admin['id'], 'create', 'page_block', $newId, 'افزودن بلوک ' . $type);
            json_response(['ok' => true, 'data' => ['id' => $newId], 'message' => 'بلوک اضافه شد.'], 201);

        case 'builder-block-save':
            if ($method !== 'PUT') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $id = (int) ($_GET['id'] ?? 0);
            if ($id < 1) api_fail('INVALID_ID', 'شناسه بلوک معتبر نیست.', 422);
            $find = $pdo->prepare("SELECT * FROM page_blocks WHERE id=? AND page_id=? LIMIT 1");
            $find->execute([$id,builder_home_page_id()]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if (!$existing) api_fail('NOT_FOUND', 'بلوک پیدا نشد.', 404);
            $data = json_body();
            $label = trim((string) ($data['label'] ?? $existing['label']));
            $content = $data['content'] ?? null;
            $settings = $data['settings'] ?? [];
            if ($label === '' || mb_strlen($label) > 140 || !is_array($content) || !is_array($settings)) {
                api_fail('VALIDATION_ERROR', 'اطلاعات بلوک معتبر نیست.', 422);
            }
            foreach ($content as $field => $value) {
                if (!is_string($field) || (!is_string($value) && !is_array($value) && !is_numeric($value) && !is_bool($value))) {
                    api_fail('VALIDATION_ERROR', 'ساختار محتوای بلوک معتبر نیست.', 422);
                }
                if (is_string($value) && mb_strlen($value) > 5000) api_fail('VALIDATION_ERROR', 'یکی از فیلدهای بلوک بیش از حد طولانی است.', 422);
            }
            $allowedStyles = ['background','text_color','padding','radius','font_size','font_weight','line_height','text_align','width','max_width','min_height','gap','border','shadow','display','responsive'];
            $cleanSettings = [];
            foreach ($allowedStyles as $key) {
                if (!array_key_exists($key, $settings)) continue;
                $value = $settings[$key];
                if (in_array($key, ['background','text_color'], true)) {
                    if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) api_fail('VALIDATION_ERROR', 'رنگ بلوک معتبر نیست.', 422);
                    $cleanSettings[$key] = $value;
                } elseif ($key === 'responsive') {
                    if (!is_array($value)) api_fail('VALIDATION_ERROR', 'تنظیمات واکنش‌گرا معتبر نیست.', 422);
                    $responsive = [];
                    $responsiveFields = ['font_size','line_height','width','max_width','min_height','gap','padding','margin','text_align','display'];
                    foreach (['tablet','mobile'] as $device) {
                        if (!isset($value[$device])) continue;
                        if (!is_array($value[$device])) api_fail('VALIDATION_ERROR', 'تنظیمات دستگاه معتبر نیست.', 422);
                        foreach ($value[$device] as $field => $setting) {
                            if (!in_array($field, $responsiveFields, true) || !is_scalar($setting)) continue;
                            $setting = trim((string) $setting);
                            if ($field === 'text_align' && !in_array($setting, ['right','left','center','justify','start','end'], true)) continue;
                            if ($field === 'display' && !in_array($setting, ['none','block','flex','grid','inline-flex'], true)) continue;
                            if (!in_array($field, ['text_align','display'], true) && !preg_match('/^-?[0-9]{1,4}(?:\\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)?$/', $setting)) continue;
                            $responsive[$device][$field] = $setting;
                        }
                    }
                    $cleanSettings['responsive'] = $responsive;
                } elseif (in_array($key, ['text_align','display','font_weight'], true)) {
                    $allowedValues = [
                        'text_align' => ['right','left','center','justify','start','end'],
                        'display' => ['block','flex','grid','inline-flex','inline-block'],
                        'font_weight' => ['100','200','300','400','500','600','700','800','900','normal','bold']
                    ];
                    $value = (string) $value;
                    if (!in_array($value, $allowedValues[$key], true)) api_fail('VALIDATION_ERROR', 'مقدار ظاهری بلوک معتبر نیست.', 422);
                    $cleanSettings[$key] = $value;
                } elseif ($key === 'border') {
                    if (!is_string($value) || !preg_match('/^(0|[0-9]{1,2}px solid #[0-9a-fA-F]{6})$/', $value)) api_fail('VALIDATION_ERROR', 'حاشیه بلوک معتبر نیست.', 422);
                    $cleanSettings[$key] = $value;
                } elseif ($key === 'shadow') {
                    if (!is_string($value) || !preg_match('/^(none|[0-9]{1,3}px [0-9]{1,3}px [0-9]{1,3}px #[0-9a-fA-F]{6})$/', $value)) api_fail('VALIDATION_ERROR', 'سایه بلوک معتبر نیست.', 422);
                    $cleanSettings[$key] = $value;
                } else {
                    if (!is_numeric($value)) {
                        if (!is_string($value) || !preg_match('/^-?[0-9]{1,4}(?:\\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)$/', trim($value))) api_fail('VALIDATION_ERROR', 'مقدار ظاهری بلوک معتبر نیست.', 422);
                        $cleanSettings[$key] = trim($value);
                    } else {
                        $cleanSettings[$key] = max(0, min($key === 'padding' ? 180 : ($key === 'font_size' ? 180 : 1600), (float) $value));
                    }
                }
            }
            $contentJson = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $settingsJson = json_encode($cleanSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($contentJson === false || $settingsJson === false || strlen($contentJson) > 200000) {
                api_fail('VALIDATION_ERROR', 'حجم محتوای بلوک بیش از حد مجاز است.', 422);
            }
            $snapshot = json_encode([
                'label' => $existing['label'],
                'content_json' => $existing['content_json'],
                'settings_json' => $existing['settings_json'],
                'is_visible' => (int) $existing['is_visible']
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $recentRevision = $pdo->prepare("SELECT COUNT(*) FROM content_revisions WHERE entity_type='block' AND entity_id=? AND created_at >= (UTC_TIMESTAMP() - INTERVAL 5 MINUTE)");
            $recentRevision->execute([$id]);
            if ((int) $recentRevision->fetchColumn() === 0) {
                $revision = $pdo->prepare("INSERT INTO content_revisions (entity_type,entity_id,snapshot_json,created_by) VALUES ('block',?,?,?)");
                $revision->execute([$id,$snapshot,(int) $admin['id']]);
            }
            $visible = !empty($data['is_visible']) ? 1 : 0;
            $save = $pdo->prepare("UPDATE page_blocks SET label=?,content_json=?,settings_json=?,is_visible=? WHERE id=?");
            $save->execute([$label,$contentJson,$settingsJson,$visible,$id]);
            $legacyTypes = ['hero','intro','projects','about','now','contact'];
            if (in_array($existing['block_type'], $legacyTypes, true) && $existing['block_key'] === $existing['block_type']) {
                $sync = $pdo->prepare("UPDATE site_sections SET content_json=?,is_visible=? WHERE section_key=?");
                $sync->execute([$contentJson,$visible,$existing['block_type']]);
            }
            if ($existing['block_type'] === 'about') {
                if (isset($content['principles_items']) && is_array($content['principles_items'])) {
                    $principlesJson = json_encode(['items' => array_slice($content['principles_items'], 0, 12)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $showPrinciples = !isset($content['show_principles']) || !empty($content['show_principles']) ? 1 : 0;
                    $syncPrinciples = $pdo->prepare("UPDATE site_sections SET content_json=?,is_visible=? WHERE section_key='principles'");
                    $syncPrinciples->execute([$principlesJson,$showPrinciples]);
                }
            }
            if ($existing['block_type'] === 'footer' && $existing['block_key'] === 'footer') {
                $syncFooter = $pdo->prepare("UPDATE site_sections SET content_json=?,is_visible=? WHERE section_key='footer'");
                $syncFooter->execute([$contentJson,$visible]);
            }
            log_activity((int) $admin['id'], 'update', 'page_block', $id, 'ویرایش بلوک ' . $label);
            json_response(['ok' => true, 'data' => [
                'id' => $id, 'label' => $label, 'content' => $content,
                'settings' => $cleanSettings, 'is_visible' => (bool) $visible
            ], 'message' => 'بلوک ذخیره شد.']);

        case 'builder-block-reorder':
            if ($method !== 'POST') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $ids = json_body()['ids'] ?? [];
            if (!is_array($ids) || count($ids) > 100) api_fail('VALIDATION_ERROR', 'فهرست مرتب‌سازی معتبر نیست.', 422);
            $pageId = builder_home_page_id();
            $current = $pdo->prepare("SELECT id,block_type,block_key FROM page_blocks WHERE page_id=? ORDER BY sort_order,id");
            $current->execute([$pageId]);
            $rows = $current->fetchAll(PDO::FETCH_ASSOC);
            $expected = array_map('intval', array_column($rows, 'id'));
            $provided = array_map('intval', $ids);
            $sortedExpected = $expected;
            $sortedProvided = $provided;
            sort($sortedExpected);
            sort($sortedProvided);
            if (count(array_unique($provided)) !== count($provided) || $sortedExpected !== $sortedProvided) {
                api_fail('VALIDATION_ERROR', 'فهرست بلوک‌ها تغییر کرده است؛ صفحه را تازه‌سازی کن.', 409);
            }
            $pdo->beginTransaction();
            try {
                $updateOrder = $pdo->prepare("UPDATE page_blocks SET sort_order=? WHERE id=? AND page_id=?");
                $syncOrder = $pdo->prepare("UPDATE site_sections SET sort_order=? WHERE section_key=?");
                $legacy = ['hero','intro','projects','about','now','contact'];
                foreach ($provided as $index => $blockId) {
                    $updateOrder->execute([$index + 1,$blockId,$pageId]);
                    foreach ($rows as $row) {
                        if ((int) $row['id'] === $blockId && in_array($row['block_type'], $legacy, true) && $row['block_key'] === $row['block_type']) {
                            $syncOrder->execute([$index + 1,$row['block_type']]);
                            break;
                        }
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            log_activity((int) $admin['id'], 'reorder', 'page_blocks', null, 'مرتب‌سازی بلوک‌های صفحه اصلی');
            json_response(['ok' => true, 'data' => [], 'message' => 'ترتیب بلوک‌ها ذخیره شد.']);

        case 'builder-block-delete':
            if ($method !== 'DELETE') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $id = (int) ($_GET['id'] ?? 0);
            $find = $pdo->prepare("SELECT block_type,block_key FROM page_blocks WHERE id=? AND page_id=? LIMIT 1");
            $find->execute([$id,builder_home_page_id()]);
            $removed = $find->fetch(PDO::FETCH_ASSOC);
            if (!$removed) api_fail('NOT_FOUND', 'بلوک پیدا نشد.', 404);
            $delete = $pdo->prepare("DELETE FROM page_blocks WHERE id=? AND page_id=?");
            $delete->execute([$id,builder_home_page_id()]);
            $legacyTypes = ['hero','intro','projects','about','now','contact'];
            if (in_array($removed['block_type'], $legacyTypes, true) && $removed['block_key'] === $removed['block_type']) {
                $hideLegacy = $pdo->prepare("UPDATE site_sections SET is_visible=0 WHERE section_key=?");
                $hideLegacy->execute([$removed['block_type']]);
            }
            if ($removed['block_type'] === 'footer' && $removed['block_key'] === 'footer') {
                $pdo->exec("UPDATE site_sections SET is_visible=0 WHERE section_key='footer'");
            }
            log_activity((int) $admin['id'], 'delete', 'page_block', $id, 'حذف بلوک صفحه اصلی');
            json_response(['ok' => true, 'data' => [], 'message' => 'بلوک حذف شد.']);

        case 'builder-block-duplicate':
            if ($method !== 'POST') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $id = (int) ($_GET['id'] ?? 0);
            $find = $pdo->prepare("SELECT * FROM page_blocks WHERE id=? AND page_id=? LIMIT 1");
            $find->execute([$id,builder_home_page_id()]);
            $source = $find->fetch(PDO::FETCH_ASSOC);
            if (!$source) api_fail('NOT_FOUND', 'بلوک پیدا نشد.', 404);
            if (in_array($source['block_type'], ['hero','intro','projects','about','now','contact','footer'], true)) {
                api_fail('DUPLICATE_NOT_SUPPORTED', 'برای جلوگیری از نمایش تکراری سکشن‌های اصلی، این بلوک قابل تکثیر نیست.', 422);
            }
            $insert = $pdo->prepare("INSERT INTO page_blocks (page_id,block_key,block_type,label,content_json,settings_json,is_visible,sort_order) VALUES (?,?,?,?,?,?,1,?)");
            $insert->execute([
                (int) $source['page_id'],
                'custom-' . bin2hex(random_bytes(6)),
                $source['block_type'],
                mb_substr((string) $source['label'] . ' - کپی', 0, 140),
                $source['content_json'],
                $source['settings_json'],
                (int) $source['sort_order'] + 1
            ]);
            $newId = (int) $pdo->lastInsertId();
            log_activity((int) $admin['id'], 'duplicate', 'page_block', $newId, 'تکثیر بلوک');
            json_response(['ok' => true, 'data' => ['id' => $newId], 'message' => 'بلوک تکثیر شد.'], 201);

        case 'builder-revisions':
            if ($method !== 'GET') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $blockId = (int) ($_GET['id'] ?? 0);
            if ($blockId < 1) api_fail('INVALID_ID', 'شناسه بلوک معتبر نیست.', 422);
            $blockCheck = $pdo->prepare('SELECT id FROM page_blocks WHERE id=? AND page_id=? LIMIT 1');
            $blockCheck->execute([$blockId,builder_home_page_id()]);
            if (!$blockCheck->fetchColumn()) api_fail('NOT_FOUND', 'بلوک پیدا نشد.', 404);
            $history = $pdo->prepare("SELECT id,created_at,created_by FROM content_revisions WHERE entity_type='block' AND entity_id=? ORDER BY created_at DESC,id DESC LIMIT 30");
            $history->execute([$blockId]);
            json_response(['ok' => true, 'data' => ['revisions' => $history->fetchAll(PDO::FETCH_ASSOC)]]);

        case 'builder-revision-restore':
            if ($method !== 'POST') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $restoreData = json_body();
            $blockId = (int) ($restoreData['block_id'] ?? 0);
            $revisionId = (int) ($restoreData['revision_id'] ?? 0);
            if ($blockId < 1 || $revisionId < 1) api_fail('INVALID_ID', 'شناسه نسخه معتبر نیست.', 422);
            $blockCheck = $pdo->prepare('SELECT * FROM page_blocks WHERE id=? AND page_id=? LIMIT 1');
            $blockCheck->execute([$blockId,builder_home_page_id()]);
            $currentBlock = $blockCheck->fetch(PDO::FETCH_ASSOC);
            if (!$currentBlock) api_fail('NOT_FOUND', 'بلوک پیدا نشد.', 404);
            $revisionStmt = $pdo->prepare("SELECT snapshot_json FROM content_revisions WHERE id=? AND entity_type='block' AND entity_id=? LIMIT 1");
            $revisionStmt->execute([$revisionId,$blockId]);
            $revisionJson = $revisionStmt->fetchColumn();
            if (!$revisionJson) api_fail('REVISION_NOT_FOUND', 'نسخه انتخاب‌شده پیدا نشد.', 404);
            $snapshot = json_decode((string) $revisionJson, true);
            if (!is_array($snapshot) || !isset($snapshot['content_json'],$snapshot['settings_json'])) api_fail('INVALID_REVISION', 'ساختار نسخه ذخیره‌شده معتبر نیست.', 409);
            $currentSnapshot = json_encode([
                'label' => $currentBlock['label'],
                'content_json' => $currentBlock['content_json'],
                'settings_json' => $currentBlock['settings_json'],
                'is_visible' => (int) $currentBlock['is_visible']
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $saveCurrent = $pdo->prepare("INSERT INTO content_revisions (entity_type,entity_id,snapshot_json,created_by) VALUES ('block',?,?,?)");
            $saveCurrent->execute([$blockId,$currentSnapshot,(int) $admin['id']]);
            $restoredContent = json_decode((string) $snapshot['content_json'], true);
            $restoredSettings = json_decode((string) $snapshot['settings_json'], true);
            if (!is_array($restoredContent) || !is_array($restoredSettings)) api_fail('INVALID_REVISION', 'محتوا یا استایل نسخه معتبر نیست.', 409);
            $restoredLabel = trim((string) ($snapshot['label'] ?? $currentBlock['label']));
            if ($restoredLabel === '') $restoredLabel = (string) $currentBlock['label'];
            $restore = $pdo->prepare('UPDATE page_blocks SET label=?,content_json=?,settings_json=?,is_visible=? WHERE id=?');
            $restore->execute([$restoredLabel,(string) $snapshot['content_json'],(string) $snapshot['settings_json'],!empty($snapshot['is_visible']) ? 1 : 0,$blockId]);
            log_activity((int) $admin['id'], 'restore', 'page_block', $blockId, 'بازیابی نسخه قبلی بلوک');
            json_response(['ok' => true, 'data' => ['id' => $blockId], 'message' => 'نسخه قبلی بازیابی شد.']);

        case 'builder-design':
            if ($method === 'GET') {
                json_response(['ok' => true, 'data' => builder_design_settings()]);
            }
            if ($method !== 'PUT') api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
            $data = json_body();
            $rules = [
                'accent_color' => ['color'],
                'page_background' => ['color'],
                'content_width' => ['int',760,1600],
                'corner_radius' => ['int',0,48],
                'font_scale' => ['float',0.85,1.2],
                'section_spacing' => ['int',32,180]
            ];
            $upsert = $pdo->prepare("INSERT INTO design_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $clean = [];
            foreach ($rules as $key => $rule) {
                if (!array_key_exists($key, $data)) continue;
                if (!is_scalar($data[$key])) api_fail('VALIDATION_ERROR', 'یکی از مقادیر ظاهری معتبر نیست.', 422);
                $value = trim((string) $data[$key]);
                if ($rule[0] === 'color') {
                    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) api_fail('VALIDATION_ERROR', 'یکی از رنگ‌ها معتبر نیست.', 422);
                } elseif ($rule[0] === 'float') {
                    if (!is_numeric($value) || (float) $value < $rule[1] || (float) $value > $rule[2]) api_fail('VALIDATION_ERROR', 'مقیاس فونت معتبر نیست.', 422);
                    $value = (string) $value;
                } else {
                    if (!preg_match('/^[0-9]+$/', $value) || (int) $value < $rule[1] || (int) $value > $rule[2]) api_fail('VALIDATION_ERROR', 'یکی از مقادیر ظاهری خارج از محدوده مجاز است.', 422);
                    $value = (string) (int) $value;
                }
                $clean[$key] = $value;
                $upsert->execute([$key,$value]);
            }
            log_activity((int) $admin['id'], 'update', 'design', null, 'ویرایش تنظیمات ظاهری');
            json_response(['ok' => true, 'data' => array_merge(builder_design_settings(), $clean), 'message' => 'تنظیمات ظاهری ذخیره شد.']);
        case 'settings':
            $allowed = ['site_title','site_description','canonical_url','og_image_url','github_url','telegram_url','avatar_path','theme_color'];
            if ($method === 'GET') {
                $settings = get_site_settings();
                json_response(['ok' => true, 'data' => array_intersect_key($settings, array_flip($allowed))]);
            }
            if ($method === 'PUT') {
                $data = json_body();
                $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
                foreach ($allowed as $key) {
                    if (array_key_exists($key, $data)) {
                        $value = trim((string) $data[$key]);
                        if (mb_strlen($value) > 2000) api_fail('VALIDATION_ERROR', 'یکی از تنظیمات بیش از حد طولانی است.', 422);
                        if ($key === 'site_title' && $value === '') api_fail('VALIDATION_ERROR', 'عنوان سایت نمی‌تواند خالی باشد.', 422);
                        if (in_array($key, ['canonical_url','og_image_url','github_url','telegram_url'], true)) {
                            $parts = parse_url($value);
                            if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
                                api_fail('VALIDATION_ERROR', 'آدرس‌های سایت و شبکه‌های اجتماعی باید HTTPS معتبر باشند.', 422, [$key => 'آدرس HTTPS معتبر وارد کن.']);
                            }
                        }
                        if ($key === 'avatar_path' && !(str_starts_with($value, '/') && !str_starts_with($value, '//')) && !(str_starts_with($value, './') && !str_contains($value, '..')) && !validate_url_value($value)) {
                            api_fail('VALIDATION_ERROR', 'مسیر تصویر پروفایل معتبر نیست.', 422);
                        }
                        if ($key === 'theme_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) api_fail('VALIDATION_ERROR', 'رنگ باید به فرمت #RRGGBB باشد.', 422);
                        $stmt->execute([$key,$value,$admin['id']]);
                    }
                }
                log_activity((int) $admin['id'], 'update', 'settings', null, 'ویرایش تنظیمات سایت');
                json_response(['ok' => true, 'data' => [], 'message' => 'تنظیمات ذخیره شد.']);
            }
            api_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
        case 'activity':
            $logs = $pdo->query('SELECT action,entity_type,summary,created_at FROM admin_activity_logs ORDER BY id DESC LIMIT 50')->fetchAll();
            json_response(['ok' => true, 'data' => $logs]);
        default:
            api_fail('NOT_FOUND', 'مسیر API پیدا نشد.', 404);
    }
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) api_fail('DUPLICATE_VALUE', 'این شناسه یا مقدار قبلاً ثبت شده است.', 409);
    error_log('Admin API database error: ' . $e->getMessage());
    api_fail('DATABASE_ERROR', 'خطایی در ارتباط با پایگاه داده رخ داد.', 500);
} catch (Throwable $e) {
    error_log('Admin API error: ' . $e->getMessage());
    api_fail('SERVER_ERROR', cfg('app.debug', false) ? $e->getMessage() : 'خطای داخلی سرور.', 500);
}
