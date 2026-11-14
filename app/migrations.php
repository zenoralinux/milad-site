<?php
declare(strict_types=1);

/**
 * Small, forward-only migration runner compatible with PHP 7.4 and MySQL 5.7+.
 * DDL is intentionally idempotent because MySQL may implicitly commit DDL.
 */
function run_app_migrations(PDO $pdo): void
{
    static $completed = false;
    if ($completed) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version INT UNSIGNED NOT NULL PRIMARY KEY,
        migration_name VARCHAR(190) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $version = (int) $pdo->query("SELECT COALESCE(MAX(version), 0) FROM schema_migrations")->fetchColumn();
    if ($version < 1) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(160) NOT NULL UNIQUE,
            title VARCHAR(180) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            seo_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pages_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS page_blocks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            block_key VARCHAR(90) NOT NULL,
            block_type VARCHAR(40) NOT NULL,
            label VARCHAR(140) NOT NULL DEFAULT '',
            content_json LONGTEXT NOT NULL,
            settings_json LONGTEXT NOT NULL,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_page_block_key (page_id, block_key),
            INDEX idx_page_blocks_order (page_id, sort_order, id),
            CONSTRAINT fk_page_blocks_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS design_settings (
            setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
            setting_value LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS reusable_blocks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL,
            block_type VARCHAR(40) NOT NULL,
            content_json LONGTEXT NOT NULL,
            settings_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_reusable_blocks_type (block_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS media_library (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            storage_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            alt_text VARCHAR(500) NOT NULL DEFAULT '',
            metadata_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_media_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS content_revisions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(40) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_content_revision_entity (entity_type, entity_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $insertPage = $pdo->prepare("INSERT IGNORE INTO pages (slug, title, status) VALUES ('home', 'صفحه اصلی', 'published')");
        $insertPage->execute();
        $pageId = (int) $pdo->query("SELECT id FROM pages WHERE slug='home' LIMIT 1")->fetchColumn();

        $known = ['hero', 'intro', 'projects', 'about', 'now', 'contact'];
        $insertBlock = $pdo->prepare("INSERT IGNORE INTO page_blocks
            (page_id, block_key, block_type, label, content_json, settings_json, is_visible, sort_order)
            VALUES (?, ?, ?, ?, ?, '{}', ?, ?)");
        $selectSections = $pdo->query("SELECT section_key, content_json, is_visible, sort_order FROM site_sections ORDER BY sort_order ASC");
        foreach ($selectSections->fetchAll(PDO::FETCH_ASSOC) as $section) {
            $key = (string) $section['section_key'];
            if (!in_array($key, $known, true)) continue;
            $labelMap = [
                'hero' => 'معرفی اصلی',
                'intro' => 'نوار معرفی',
                'projects' => 'پروژه‌ها',
                'about' => 'درباره من',
                'now' => 'اکنون',
                'contact' => 'ارتباط',
            ];
            $insertBlock->execute([
                $pageId, $key, $key, $labelMap[$key],
                (string) $section['content_json'],
                (int) $section['is_visible'],
                (int) $section['sort_order']
            ]);
        }

        $defaults = [
            'accent_color' => '#a58aff',
            'page_background' => '#080b12',
            'content_width' => '1160',
            'corner_radius' => '22',
            'font_scale' => '1',
            'section_spacing' => '72'
        ];
        $insertDesign = $pdo->prepare("INSERT IGNORE INTO design_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaults as $key => $value) $insertDesign->execute([$key, $value]);

        $mark = $pdo->prepare("INSERT IGNORE INTO schema_migrations (version, migration_name) VALUES (1, 'visual_page_builder')");
        $mark->execute();
        $version = 1;
    }

    if ($version < 2) {
        $pageId = (int) $pdo->query("SELECT id FROM pages WHERE slug='home' LIMIT 1")->fetchColumn();
        $sections = $pdo->query("SELECT section_key,content_json,is_visible,sort_order FROM site_sections")->fetchAll(PDO::FETCH_ASSOC);
        $sectionMap = [];
        foreach ($sections as $section) $sectionMap[(string) $section['section_key']] = $section;

        $about = isset($sectionMap['about']) ? json_decode((string) $sectionMap['about']['content_json'], true) : [];
        $about = is_array($about) ? $about : [];
        $principles = isset($sectionMap['principles']) ? json_decode((string) $sectionMap['principles']['content_json'], true) : [];
        $about['principles_items'] = is_array($principles) && isset($principles['items']) && is_array($principles['items']) ? $principles['items'] : [];
        $about['show_principles'] = !isset($sectionMap['principles']) || (int) $sectionMap['principles']['is_visible'] === 1;
        if (!isset($about['link_url']) || trim((string) $about['link_url']) === '') {
            $githubUrl = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='github_url' LIMIT 1")->fetchColumn();
            $about['link_url'] = $githubUrl ? (string) $githubUrl : 'https://github.com/miladalizadw';
        }
        $aboutJson = json_encode($about, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($aboutJson !== false) {
            $updateAbout = $pdo->prepare("UPDATE page_blocks SET content_json=? WHERE page_id=? AND block_key='about' AND block_type='about'");
            $updateAbout->execute([$aboutJson,$pageId]);
        }

        if (isset($sectionMap['footer'])) {
            $footer = json_decode((string) $sectionMap['footer']['content_json'], true);
            $footer = is_array($footer) ? $footer : [];
            $insertFooter = $pdo->prepare("INSERT IGNORE INTO page_blocks
                (page_id,block_key,block_type,label,content_json,settings_json,is_visible,sort_order)
                VALUES (?,'footer','footer','پاورقی سایت',?,'{}',?,?)");
            $insertFooter->execute([
                $pageId,
                json_encode($footer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int) $sectionMap['footer']['is_visible'],
                (int) $sectionMap['footer']['sort_order']
            ]);
        }

        $pdo->exec("INSERT IGNORE INTO schema_migrations (version,migration_name) VALUES (2,'live_visual_editor')");
        $version = 2;
    }

    $completed = true;
}
