<?php
declare(strict_types=1);

function builder_home_page_id(): int
{
    $id = (int) db()->query("SELECT id FROM pages WHERE slug='home' LIMIT 1")->fetchColumn();
    return $id;
}

function builder_home_blocks(): array
{
    $pageId = builder_home_page_id();
    if ($pageId < 1) return [];
    $stmt = db()->prepare("SELECT id, block_key, block_type, label, content_json, settings_json, is_visible, sort_order
        FROM page_blocks WHERE page_id=? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$pageId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $content = json_decode((string) $row['content_json'], true);
        $settings = json_decode((string) $row['settings_json'], true);
        $row['content'] = is_array($content) ? $content : [];
        $row['settings'] = is_array($settings) ? $settings : [];
        $row['is_visible'] = (bool) $row['is_visible'];
        unset($row['content_json'], $row['settings_json']);
    }
    return $rows;
}

function builder_design_settings(): array
{
    $rows = db()->query("SELECT setting_key, setting_value FROM design_settings")->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
    return $settings;
}

function builder_valid_color($value, string $fallback): string
{
    $value = trim((string) $value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
}

function builder_design_css(array $settings): string
{
    $accent = builder_valid_color($settings['accent_color'] ?? '', '#a58aff');
    $background = builder_valid_color($settings['page_background'] ?? '', '#080b12');
    $width = max(760, min(1600, (int) ($settings['content_width'] ?? 1160)));
    $radius = max(0, min(48, (int) ($settings['corner_radius'] ?? 22)));
    $scale = max(0.85, min(1.2, (float) ($settings['font_scale'] ?? 1)));
    $spacing = max(32, min(180, (int) ($settings['section_spacing'] ?? 72)));
    return '<style id="builder-design-tokens">:root{--accent:' . $accent . ';--accent-strong:' . $accent .
        ';--container:' . $width . 'px;--radius-lg:' . $radius . 'px;--radius-md:' . $radius .
        'px;--radius-sm:' . max(0, (int) round($radius * 0.68)) . 'px;--builder-font-scale:' . $scale .
        ';--builder-section-gap:' . $spacing . 'px}html[data-theme="dark"]{--bg:' . $background . '}' .
        'body{font-size:calc(15px * var(--builder-font-scale,1))}' .
        '.section-shell{scroll-margin-top:110px}' .
        '.work-section,.about-section,.now-section,.contact-section{padding-block:var(--builder-section-gap,72px)}' .
        '.builder-custom-block .panel-card{padding:clamp(20px,4vw,44px);border:1px solid var(--line);border-radius:var(--radius-lg)}' .
        '.builder-custom-block h2{margin:0 0 12px;font-size:clamp(24px,4vw,42px)}' .
        '.builder-custom-block p{color:var(--muted);line-height:2.1}' .
        '</style>';
}

function builder_extract_section(string &$html, string $marker): string
{
    $start = strpos($html, $marker);
    if ($start === false) return '';
    $tail = substr($html, $start);
    if (!preg_match_all('/<section\b[^>]*>|<\/section>/i', $tail, $matches, PREG_OFFSET_CAPTURE)) return '';
    $depth = 0;
    $end = null;
    foreach ($matches[0] as $match) {
        $tag = $match[0];
        if (stripos($tag, '</section') === 0) {
            $depth--;
            if ($depth === 0) {
                $end = $match[1] + strlen($tag);
                break;
            }
        } else {
            $depth++;
        }
    }
    if ($end === null) return '';
    $fragment = substr($html, $start, $end);
    $html = substr($html, 0, $start) . substr($html, $start + $end);
    return $fragment;
}

function builder_block_style(array $settings): string
{
    $styles = [];
    if (isset($settings['background']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $settings['background'])) {
        $styles[] = 'background-color:' . $settings['background'];
    }
    if (isset($settings['text_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $settings['text_color'])) {
        $styles[] = 'color:' . $settings['text_color'];
    }
    if (isset($settings['padding'])) {
        $padding = max(0, min(180, (int) $settings['padding']));
        $styles[] = 'padding-block:' . $padding . 'px';
    }
    if (isset($settings['radius'])) {
        $radius = max(0, min(48, (int) $settings['radius']));
        $styles[] = 'border-radius:' . $radius . 'px';
    }
    return $styles ? ' style="' . h(implode(';', $styles)) . '"' : '';
}

function builder_render_custom_block(array $block): string
{
    $type = (string) ($block['block_type'] ?? '');
    $content = $block['content'];
    $style = builder_block_style($block['settings']);
    $title = h($content['title'] ?? '');
    $body = nl2br(h($content['body'] ?? ''));
    $id = 'block-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $block['block_key']);

    if ($type === 'text') {
        return '<section class="section-shell builder-custom-block builder-text-block" id="' . $id . '"' . $style .
            '><div class="container"><div class="section-heading"><h2>' . $title . '</h2><p>' . $body . '</p></div></div></section>';
    }
    if ($type === 'cta') {
        $buttonText = h($content['button_text'] ?? 'تماس با من');
        $url = (string) ($content['button_url'] ?? '#contact');
        $safeUrl = '#contact';
        if (preg_match('/^#[a-zA-Z0-9_-]+$/', $url)) {
            $safeUrl = $url;
        } elseif (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $safeUrl = app_url($url);
        } elseif (preg_match('#^https://[a-z0-9.-]+(?::[0-9]{1,5})?(?:/.*)?$#i', $url)) {
            $safeUrl = $url;
        }
        return '<section class="section-shell builder-custom-block builder-cta-block"' . $style .
            '><div class="container"><div class="panel-card"><h2>' . $title . '</h2><p>' . $body .
            '</p><a class="button button-primary" href="' . h($safeUrl) . '">' . $buttonText . '</a></div></div></section>';
    }
    if ($type === 'stats') {
        $items = isset($content['items']) && is_array($content['items']) ? $content['items'] : [];
        $cards = '';
        foreach (array_slice($items, 0, 6) as $item) {
            if (!is_array($item)) continue;
            $cards .= '<article class="principle-card"><span class="principle-icon">' . h($item['value'] ?? '') .
                '</span><h3>' . h($item['label'] ?? '') . '</h3><p>' . h($item['description'] ?? '') . '</p></article>';
        }
        return '<section class="section-shell builder-custom-block builder-stats-block"' . $style .
            '><div class="container">' . ($title !== '' ? '<h2>' . $title . '</h2>' : '') .
            '<div class="principles-grid">' . $cards . '</div></div></section>';
    }
    if ($type === 'spacer') {
        $height = max(8, min(240, (int) ($content['height'] ?? 48)));
        return '<div class="builder-spacer" aria-hidden="true" style="height:' . $height . 'px"></div>';
    }
    return '';
}

/**
 * The original homepage template remains the visual shell. Its existing sections are
 * extracted and reassembled in database order; new safe block types are rendered here.
 */
function builder_apply_to_html(string $html, array $blocks, array $design): string
{
    $markers = [
        'hero' => '<section class="hero section-shell" id="home">',
        'intro' => '<section class="intro-strip">',
        'projects' => '<section class="work-section section-shell" id="work">',
        'about' => '<section class="about-section section-shell" id="about">',
        'now' => '<section class="now-section section-shell" id="now">',
        'contact' => '<section class="contact-section section-shell" id="contact">',
    ];
    $fragments = [];
    foreach ($markers as $key => $marker) $fragments[$key] = builder_extract_section($html, $marker);

    $rendered = '';
    foreach ($blocks as $block) {
        if (empty($block['is_visible'])) continue;
        $type = (string) $block['block_type'];
        $fragment = $fragments[$type] ?? '';
        if ($fragment !== '') {
            $style = builder_block_style($block['settings']);
            if ($style !== '') {
                $fragment = preg_replace('/^<section\b([^>]*)>/i', '<section$1' . $style . '>', $fragment, 1) ?? $fragment;
            }
            $rendered .= $fragment . "\n";
            continue;
        }
        $rendered .= builder_render_custom_block($block) . "\n";
    }

    $main = strpos($html, '<main id="main">');
    if ($main !== false) {
        $contentStart = strpos($html, '>', $main) + 1;
        $contentEnd = strrpos($html, '</main>');
        if ($contentStart > 0 && $contentEnd !== false && $contentEnd >= $contentStart) {
            $html = substr($html, 0, $contentStart) . "\n" . $rendered . "\n" . substr($html, $contentEnd);
        }
    }

    $css = builder_design_css($design);
    $headEnd = stripos($html, '</head>');
    if ($headEnd !== false) $html = substr($html, 0, $headEnd) . $css . substr($html, $headEnd);
    return $html;
}
