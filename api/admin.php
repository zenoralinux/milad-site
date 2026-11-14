<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
start_admin_session();

function api_fail(string $code, string $message, int $status = 400, array $fields = []): never
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
