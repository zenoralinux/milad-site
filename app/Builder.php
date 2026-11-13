<?php
declare(strict_types=1);

function handle_builder(): void
{
    require_permission('settings.manage');
    $allowed = [
        'hero' => 'بخش معرفی اصلی',
        'intro' => 'نوار معرفی کوتاه',
        'projects' => 'ویترین پروژه‌ها',
        'about' => 'درباره من',
        'contact' => 'راه‌های ارتباطی',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $raw = json_decode((string)($_POST['layout_json'] ?? '[]'), true);
        if (!is_array($raw) || count($raw) !== count($allowed)) {
            flash('error', 'ساختار چیدمان معتبر نیست؛ صفحه را تازه‌سازی کن.');
            redirect('admin/builder');
        }
        $layout = [];
        $seen = [];
        foreach ($raw as $item) {
            $id = (string)($item['id'] ?? '');
            if (!isset($allowed[$id]) || isset($seen[$id])) continue;
            $seen[$id] = true;
            $layout[] = ['id' => $id, 'enabled' => (bool)($item['enabled'] ?? false)];
        }
        foreach ($allowed as $id => $label) {
            if (!isset($seen[$id])) $layout[] = ['id' => $id, 'enabled' => true];
        }
        $stmt = db()->prepare('INSERT INTO settings(setting_key,setting_value,updated_at) VALUES("homepage_layout",:v,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');
        $stmt->execute(['v' => json_encode($layout, JSON_UNESCAPED_UNICODE)]);
        log_activity('builder.homepage.update', 'theme');
        flash('success', 'چیدمان صفحه اصلی ذخیره شد.');
        redirect('admin/builder');
    }

    $saved = json_decode((string)(settings_all()['homepage_layout'] ?? '[]'), true);
    if (!is_array($saved) || !$saved) {
        $saved = [];
        foreach (array_keys($allowed) as $id) $saved[] = ['id' => $id, 'enabled' => true];
    }
    $byId = [];
    foreach ($saved as $item) {
        $id = (string)($item['id'] ?? '');
        if (isset($allowed[$id])) $byId[$id] = ['id' => $id, 'enabled' => (bool)($item['enabled'] ?? true)];
    }
    foreach ($allowed as $id => $label) {
        if (!isset($byId[$id])) $byId[$id] = ['id' => $id, 'enabled' => true];
    }
    $items = [];
    foreach ($byId as $item) $items[] = $item + ['label' => $allowed[$item['id']]];
    render_view('admin/builder', ['items' => $items, 'pageTitle' => 'صفحه‌ساز بصری']);
}
