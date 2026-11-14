<?php
declare(strict_types=1);

function builder_home_page_id(): int
{
    return (int) db()->query("SELECT id FROM pages WHERE slug='home' LIMIT 1")->fetchColumn();
}

function builder_home_blocks(): array
{
    $pageId = builder_home_page_id();
    if ($pageId < 1) return [];
    $stmt = db()->prepare("SELECT id,block_key,block_type,label,content_json,settings_json,is_visible,sort_order
        FROM page_blocks WHERE page_id=? ORDER BY sort_order ASC,id ASC");
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
    $rows = db()->query("SELECT setting_key,setting_value FROM design_settings")->fetchAll(PDO::FETCH_ASSOC);
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
        ';--bg:' . $background . ';--container:' . $width . 'px;--radius-lg:' . $radius .
        'px;--radius-md:' . $radius . 'px;--radius-sm:' . max(0, (int) round($radius * 0.68)) .
        'px;--builder-font-scale:' . $scale . ';--builder-section-gap:' . $spacing . 'px}' .
        'body{font-size:calc(15px * var(--builder-font-scale,1));background-color:var(--bg)}' .
        '.section-shell{scroll-margin-top:110px}' .
        '.work-section,.about-section,.now-section,.contact-section{padding-block:var(--builder-section-gap,72px)}' .
        '.builder-custom-block .panel-card{padding:clamp(20px,4vw,44px);border:1px solid var(--line);border-radius:var(--radius-lg)}' .
        '.builder-custom-block h2{margin:0 0 12px;font-size:clamp(24px,4vw,42px)}' .
        '.builder-custom-block p{color:var(--muted);line-height:2.1}' .
        '.builder-layout-container{display:flex;gap:var(--builder-layout-gap,20px);min-width:0}.builder-layout-container.is-row{flex-direction:row}.builder-layout-container.is-column{flex-direction:column}.builder-layout-grid{display:grid;grid-template-columns:repeat(var(--builder-grid-columns,2),minmax(0,1fr));gap:18px}.builder-widget-card{border:1px solid var(--line);border-radius:var(--radius-lg);padding:22px;background:rgba(255,255,255,.035)}.builder-widget-image img{display:block;width:100%;height:auto;border-radius:inherit}.builder-widget-video iframe{display:block;width:100%;aspect-ratio:16/9;border:0;border-radius:var(--radius-lg)}.builder-widget-image-placeholder{padding:36px;border:1px dashed var(--line);border-radius:14px;text-align:center;color:var(--muted)}.builder-widget-counter{display:grid;gap:5px}.builder-widget-counter strong{font-size:clamp(28px,4vw,44px)}.builder-widget-list{padding-inline-start:24px;line-height:2}.builder-widget-divider{border:0;border-top:1px solid var(--line);width:100%}' .
        '@media(max-width:760px){.builder-layout-container.is-row{flex-direction:column}.builder-layout-grid{grid-template-columns:repeat(min(var(--builder-grid-columns,2),2),minmax(0,1fr))}}@media(max-width:460px){.builder-layout-grid{grid-template-columns:1fr}}' .
        '[data-builder-key][hidden],.principles-grid[hidden]{display:none!important}' .
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
        if (stripos($match[0], '</section') === 0) {
            $depth--;
            if ($depth === 0) {
                $end = $match[1] + strlen($match[0]);
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

function builder_extract_footer(string &$html): string
{
    $start = strpos($html, '<footer class="site-footer">');
    if ($start === false) return '';
    $endTag = '</footer>';
    $end = strpos($html, $endTag, $start);
    if ($end === false) return '';
    $end += strlen($endTag);
    $fragment = substr($html, $start, $end - $start);
    $html = substr($html, 0, $start) . substr($html, $end);
    return $fragment;
}

function builder_block_style(array $settings): string
{
    $styles = [];
    $legacy = [
        'background' => 'background-color',
        'text_color' => 'color',
        'padding' => 'padding-block',
        'radius' => 'border-radius'
    ];
    foreach ($legacy as $key => $property) {
        if (!isset($settings[$key])) continue;
        if (in_array($key, ['background','text_color'], true)) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $settings[$key])) continue;
            $styles[] = $property . ':' . $settings[$key];
        } else {
            $max = $key === 'padding' ? 180 : 48;
            $styles[] = $property . ':' . max(0, min($max, (int) $settings[$key])) . 'px';
        }
    }
    $advanced = builder_widget_style($settings);
    if ($advanced !== '') {
        $advanced = substr($advanced, 8, -1);
        if ($advanced !== '') $styles[] = $advanced;
    }
    return $styles ? ' style="' . h(implode(';', $styles)) . '"' : '';
}


function builder_widget_safe_url($value, bool $image = false): string
{
    $url = trim((string) $value);
    if ($url === '') return '';
    if (preg_match('/^#[a-zA-Z0-9_-]+$/', $url)) return $url;
    if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) return $url;
    if ($image && preg_match('#^data:image/(?:png|jpe?g|webp|gif);base64,[a-zA-Z0-9+/=]+$#i', $url)) return $url;
    if (preg_match('#^https://[a-z0-9.-]+(?::[0-9]{1,5})?(?:/[^\\s]*)?$#i', $url)) return $url;
    return '';
}

function builder_widget_style(array $styles): string
{
    $map = [
        'background' => 'background-color', 'color' => 'color', 'text_color' => 'color',
        'font_size' => 'font-size', 'font_weight' => 'font-weight', 'line_height' => 'line-height',
        'text_align' => 'text-align', 'width' => 'width', 'max_width' => 'max-width',
        'min_height' => 'min-height', 'gap' => 'gap', 'padding' => 'padding', 'margin' => 'margin',
        'radius' => 'border-radius', 'border' => 'border', 'shadow' => 'box-shadow', 'display' => 'display'
    ];
    $out = [];
    foreach ($map as $key => $property) {
        if (!isset($styles[$key]) || !is_scalar($styles[$key])) continue;
        $value = trim((string) $styles[$key]);
        if ($value === '') continue;
        if (in_array($key, ['background','color','text_color'], true)) {
            if (!preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $value)) continue;
        } elseif ($key === 'text_align') {
            if (!in_array($value, ['right','left','center','justify','start','end'], true)) continue;
        } elseif ($key === 'font_weight') {
            if (!preg_match('/^(100|200|300|400|500|600|700|800|900|normal|bold)$/', $value)) continue;
        } elseif ($key === 'display') {
            if (!in_array($value, ['block','flex','grid','inline-flex','inline-block'], true)) continue;
        } elseif ($key === 'border') {
            if (!preg_match('/^(0|[0-9]{1,2}px solid #[0-9a-fA-F]{6})$/', $value)) continue;
        } elseif ($key === 'shadow') {
            if (!preg_match('/^(?:none|(?:0|[0-9]{1,3}px)\\s+(?:0|[0-9]{1,3}px)\\s+(?:0|[0-9]{1,3}px)\\s*(?:#[0-9a-fA-F]{6}|rgba\\([0-9, .]+\\)))$/', $value)) continue;
        } elseif (in_array($key, ['padding','margin'], true)) {
            $length = '(?:-?[0-9]{1,4}(?:\\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw)?|auto)';
            if (!preg_match('/^' . $length . '(?:\\s+' . $length . '){0,3}$/', $value)) continue;
        } else {
            if (!preg_match('/^-?[0-9]{1,4}(?:\\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)?$/', $value)) continue;
        }
        $out[] = $property . ':' . $value;
    }
    return $out ? ' style="' . h(implode(';', $out)) . '"' : '';
}

function builder_render_nested_widget(array $widget, int $depth = 0): string
{
    if ($depth > 8) return '';
    $type = (string) ($widget['type'] ?? 'paragraph');
    $allowedWidgetTypes = ['container','grid','card','heading','paragraph','button','image','divider','icon','counter','list','video','feature','pricing','accordion','testimonial','gallery','timeline','skill','logo_cloud','team','tabs','newsletter','icon_box'];
    if (!in_array($type, $allowedWidgetTypes, true)) return '';
    $content = is_array($widget['content'] ?? null) ? $widget['content'] : $widget;
    $styles = is_array($widget['styles'] ?? null) ? $widget['styles'] : [];
    $children = is_array($widget['children'] ?? null) ? $widget['children'] : [];
    $style = builder_widget_style($styles);
    $nodeId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($widget['id'] ?? ''));
    $nodeAttr = $nodeId !== '' ? ' data-widget-node="' . h($nodeId) . '"' : '';
    $inner = '';
    foreach (array_slice($children, 0, 40) as $child) {
        if (is_array($child)) $inner .= builder_render_nested_widget($child, $depth + 1);
    }
    if ($type === 'container') {
        $direction = (($content['direction'] ?? 'column') === 'row') ? 'row' : 'column';
        return '<div class="builder-layout-container is-' . $direction . '"' . $nodeAttr . $style . '>' . $inner . '</div>';
    }
    if ($type === 'grid') {
        $columns = max(1, min(6, (int) ($content['columns'] ?? 2)));
        $styleValue = '--builder-grid-columns:' . $columns;
        if ($style !== '') $styleValue .= ';' . substr($style, 8, -1);
        return '<div class="builder-layout-grid"' . $nodeAttr . ' style="' . h($styleValue) . '">' . $inner . '</div>';
    }
    if ($type === 'card') {
        return '<article class="builder-widget-card"' . $nodeAttr . $style . '><div class="builder-widget-card-content"><h3>' .
            h($content['title'] ?? 'عنوان کارت') . '</h3><p>' . nl2br(h($content['body'] ?? 'توضیحات کارت')) .
            '</p>' . $inner . '</div></article>';
    }
    if ($type === 'heading') {
        $level = max(1, min(6, (int) ($content['level'] ?? 2)));
        return '<h' . $level . ' class="builder-widget-heading"' . $nodeAttr . $style . '>' . h($content['text'] ?? 'عنوان جدید') . '</h' . $level . '>';
    }
    if ($type === 'paragraph') return '<p class="builder-widget-paragraph"' . $nodeAttr . $style . '>' . nl2br(h($content['text'] ?? 'متن جدید')) . '</p>';
    if ($type === 'button') {
        $url = builder_widget_safe_url($content['url'] ?? '#contact');
        return '<a class="button button-primary builder-widget-button"' . $nodeAttr . $style .
            ' href="' . h($url !== '' ? $url : '#') . '">' . h($content['text'] ?? 'دکمه جدید') . '</a>';
    }
    if ($type === 'image') {
        $src = builder_widget_safe_url($content['src'] ?? '', true);
        if ($src === '') return '<div class="builder-widget-image-placeholder"' . $nodeAttr . '>تصویر انتخاب نشده است</div>';
        return '<figure class="builder-widget-image"' . $nodeAttr . $style . '><img src="' . h($src) .
            '" alt="' . h($content['alt'] ?? '') . '" loading="lazy"></figure>';
    }
    if ($type === 'divider') return '<hr class="builder-widget-divider"' . $nodeAttr . $style . '>';
    if ($type === 'icon') return '<span class="builder-widget-icon"' . $nodeAttr . $style . ' aria-hidden="true">' . h($content['symbol'] ?? '✦') . '</span>';
    if ($type === 'counter') {
        return '<div class="builder-widget-counter"' . $nodeAttr . $style . '><strong>' . h($content['value'] ?? '0') .
            '</strong><span>' . h($content['label'] ?? 'عنوان آمار') . '</span></div>';
    }
    if ($type === 'list') {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $list = '';
        foreach (array_slice($items, 0, 30) as $item) $list .= '<li>' . h(is_array($item) ? ($item['text'] ?? '') : $item) . '</li>';
        return '<ul class="builder-widget-list"' . $nodeAttr . $style . '>' . $list . '</ul>';
    }
    if ($type === 'feature' || $type === 'icon_box') {
        return '<article class="builder-widget-feature"' . $nodeAttr . $style . '><span class="builder-feature-icon">' . h($content['symbol'] ?? '✦') . '</span><h3>' . h($content['title'] ?? 'ویژگی جدید') . '</h3><p>' . nl2br(h($content['description'] ?? 'توضیحات ویژگی')) . '</p>' . $inner . '</article>';
    }
    if ($type === 'pricing') {
        $features = is_array($content['items'] ?? null) ? $content['items'] : [];
        $list = '';
        foreach (array_slice($features, 0, 20) as $feature) $list .= '<li>' . h(is_array($feature) ? ($feature['text'] ?? $feature['title'] ?? '') : $feature) . '</li>';
        $url = builder_widget_safe_url($content['button_url'] ?? '#contact');
        return '<article class="builder-widget-pricing"' . $nodeAttr . $style . '><h3>' . h($content['title'] ?? 'پلن') . '</h3><strong class="builder-pricing-price">' . h($content['price'] ?? '') . '</strong><small>' . h($content['period'] ?? '') . '</small><p>' . h($content['description'] ?? '') . '</p><ul>' . $list . '</ul><a class="button button-primary" href="' . h($url ?: '#contact') . '">' . h($content['button_text'] ?? 'انتخاب پلن') . '</a></article>';
    }
    if ($type === 'accordion') {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $list = '';
        foreach (array_slice($items, 0, 30) as $item) if (is_array($item)) $list .= '<details class="builder-widget-faq"><summary>' . h($item['question'] ?? $item['title'] ?? '') . '</summary><p>' . nl2br(h($item['answer'] ?? $item['description'] ?? '')) . '</p></details>';
        return '<div class="builder-widget-accordion"' . $nodeAttr . $style . '>' . $list . '</div>';
    }
    if ($type === 'testimonial') {
        return '<figure class="builder-widget-testimonial"' . $nodeAttr . $style . '><blockquote>' . nl2br(h($content['quote'] ?? 'نظر مشتری')) . '</blockquote><figcaption><strong>' . h($content['author'] ?? 'نام مشتری') . '</strong><span>' . h($content['role'] ?? '') . '</span></figcaption></figure>';
    }
    if ($type === 'gallery') {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $tiles = '';
        foreach (array_slice($items, 0, 24) as $item) {
            if (!is_array($item)) continue;
            $src = builder_widget_safe_url($item['src'] ?? '', true);
            $tiles .= $src !== '' ? '<figure><img src="' . h($src) . '" alt="' . h($item['alt'] ?? '') . '" loading="lazy"><figcaption>' . h($item['title'] ?? '') . '</figcaption></figure>' : '<div class="builder-gallery-placeholder">' . h($item['title'] ?? 'تصویر را انتخاب کن') . '</div>';
        }
        return '<section class="builder-widget-gallery"' . $nodeAttr . $style . '>' . (!empty($content['title']) ? '<h3>' . h($content['title']) . '</h3>' : '') . $tiles . '</section>';
    }
    if ($type === 'timeline') {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $list = '';
        foreach (array_slice($items, 0, 30) as $item) if (is_array($item)) $list .= '<article><time>' . h($item['date'] ?? '') . '</time><div><h3>' . h($item['title'] ?? '') . '</h3><p>' . h($item['description'] ?? '') . '</p></div></article>';
        return '<div class="builder-widget-timeline"' . $nodeAttr . $style . '>' . (!empty($content['title']) ? '<h3>' . h($content['title']) . '</h3>' : '') . $list . '</div>';
    }
    if ($type === 'skill') {
        $value = max(0, min(100, (int) ($content['value'] ?? 75)));
        return '<div class="builder-widget-skill"' . $nodeAttr . $style . '><div><strong>' . h($content['title'] ?? 'مهارت') . '</strong><span>' . $value . '%</span></div><i><b style="width:' . $value . '%"></b></i></div>';
    }
    if ($type === 'logo_cloud' || $type === 'team' || $type === 'tabs') {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $cards = '';
        foreach (array_slice($items, 0, 24) as $item) {
            if (!is_array($item)) continue;
            if ($type === 'logo_cloud') {
                $src = builder_widget_safe_url($item['src'] ?? '', true);
                $cards .= '<div class="builder-logo-item">' . ($src !== '' ? '<img src="' . h($src) . '" alt="' . h($item['alt'] ?? ($item['name'] ?? 'logo')) . '">' : '<strong>' . h($item['name'] ?? 'Brand') . '</strong>') . '</div>';
            } elseif ($type === 'team') {
                $src = builder_widget_safe_url($item['src'] ?? '', true);
                $cards .= '<article class="builder-team-card">' . ($src !== '' ? '<img src="' . h($src) . '" alt="' . h($item['name'] ?? '') . '">' : '<span class="builder-team-avatar">✦</span>') . '<h3>' . h($item['name'] ?? '') . '</h3><p>' . h($item['role'] ?? '') . '</p></article>';
            } else {
                $cards .= '<article class="builder-tab-card"><h3>' . h($item['title'] ?? '') . '</h3><p>' . h($item['description'] ?? '') . '</p></article>';
            }
        }
        $class = $type === 'logo_cloud' ? 'builder-widget-logos' : ($type === 'team' ? 'builder-widget-team' : 'builder-widget-tabs');
        return '<section class="' . $class . '"' . $nodeAttr . $style . '>' . (!empty($content['title']) ? '<h3>' . h($content['title']) . '</h3>' : '') . $cards . '</section>';
    }
    if ($type === 'newsletter') {
        return '<section class="builder-widget-newsletter"' . $nodeAttr . $style . '><h3>' . h($content['title'] ?? 'عضویت در خبرنامه') . '</h3><p>' . h($content['description'] ?? '') . '</p><form><input type="email" placeholder="' . h($content['placeholder'] ?? 'ایمیل شما') . '" disabled><button type="button" disabled>' . h($content['button_text'] ?? 'عضویت') . '</button></form></section>';
    }
    if ($type === 'video') {
        $url = (string) ($content['url'] ?? '');
        if (preg_match('#^https://www\\.youtube\\.com/embed/[a-zA-Z0-9_-]+(?:\\?[a-zA-Z0-9_=&-]+)?$#', $url) ||
            preg_match('#^https://player\\.vimeo\\.com/video/[0-9]+(?:\\?[a-zA-Z0-9_=&-]+)?$#', $url)) {
            return '<div class="builder-widget-video"' . $nodeAttr . $style . '><iframe src="' . h($url) .
                '" title="' . h($content['title'] ?? 'ویدیو') . '" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
        }
        return '<div class="builder-widget-image-placeholder"' . $nodeAttr . '>آدرس ویدیو معتبر نیست</div>';
    }
    return '';
}


function builder_legacy_element_css(array $blocks): string
{
    $selectors = [
        'hero' => [
            'eyebrow' => '.hero-copy .eyebrow', 'title_line1' => '.hero-copy h1',
            'title_line2' => '.hero-copy h1 .type-gradient', 'description' => '.hero-description',
            'primary_cta_text' => '.hero-actions a:first-child',
            'secondary_cta_text' => '.hero-actions a:nth-child(2)', 'footnote' => '.hero-footnote span:last-child'
        ],
        'intro' => ['text_before' => '.intro-strip-inner p', 'text_emphasis' => '.intro-strip-inner p span'],
        'projects' => [
            'heading_before' => '.section-heading h2', 'heading_emphasis' => '.section-heading h2 span',
            'description' => '.section-heading > p'
        ],
        'about' => [
            'section_index' => '.about-heading .section-index', 'title_line1' => '.about-heading h2',
            'title_line2' => '.about-heading h2 .type-gradient', 'description' => '.about-heading > p',
            'link_text' => '.about-heading .text-link'
        ],
        'now' => [
            'section_index' => '.now-panel-copy .section-index', 'title_line1' => '.now-panel-copy h2',
            'title_line2' => '.now-panel-copy h2 .type-gradient', 'description' => '.now-panel-copy > p',
            'status_title' => '.now-note strong', 'status_subtitle' => '.now-note small'
        ],
        'contact' => [
            'section_index' => '.contact-copy .section-index', 'title_line1' => '.contact-copy h2',
            'title_line2' => '.contact-copy h2 .type-gradient', 'description' => '.contact-copy > p'
        ],
        'footer' => ['tagline' => '.footer-brand > span:last-child', 'copyright' => '.footer-copy'],
        'text' => ['title' => '.section-heading h2', 'body' => '.section-heading > p'],
        'cta' => ['title' => '.panel-card h2', 'body' => '.panel-card > p', 'button_text' => '.panel-card a.button'],
        'stats' => ['title' => '.container > h2']
    ];
    $propertyMap = [
        'font_size' => 'font-size', 'line_height' => 'line-height', 'width' => 'width',
        'max_width' => 'max-width', 'min_height' => 'min-height', 'gap' => 'gap',
        'padding' => 'padding', 'margin' => 'margin', 'text_align' => 'text-align',
        'display' => 'display'
    ];
    $queries = ['tablet' => '(max-width: 1024px)', 'mobile' => '(max-width: 767px)'];
    $output = '';
    $responsiveOutput = ['tablet' => '', 'mobile' => ''];
    foreach ($blocks as $block) {
        $type = (string) ($block['block_type'] ?? '');
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($block['block_key'] ?? ''));
        $styles = $block['content']['element_styles'] ?? [];
        if ($key === '' || !isset($selectors[$type]) || !is_array($styles)) continue;
        foreach ($selectors[$type] as $field => $selector) {
            if (!isset($styles[$field]) || !is_array($styles[$field])) continue;
            $style = builder_widget_style($styles[$field]);
            if ($style !== '') {
                $declarations = substr($style, 8, -1);
                if ($declarations !== '') $output .= '[data-builder-key="' . $key . '"] ' . $selector . '{' . $declarations . '}';
            }
            foreach ($queries as $device => $query) {
                $responsive = $styles[$field]['responsive'][$device] ?? [];
                if (!is_array($responsive)) continue;
                $declarations = [];
                foreach ($propertyMap as $propertyKey => $property) {
                    if (!isset($responsive[$propertyKey]) || !is_scalar($responsive[$propertyKey])) continue;
                    $value = trim((string) $responsive[$propertyKey]);
                    if ($propertyKey === 'text_align') {
                        if (!in_array($value, ['right','left','center','justify','start','end'], true)) continue;
                    } elseif ($propertyKey === 'display') {
                        if (!in_array($value, ['none','block','flex','grid','inline-flex'], true)) continue;
                    } elseif (!preg_match('/^-?[0-9]{1,4}(?:\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)?$/', $value)) {
                        continue;
                    }
                    $declarations[] = $property . ':' . $value;
                }
                if ($declarations) {
                    $responsiveOutput[$device] .= '[data-builder-key="' . $key . '"] ' . $selector .
                        '{' . implode(';', $declarations) . '}';
                }
            }
        }
    }
    foreach ($responsiveOutput as $device => $rules) {
        if ($rules !== '') $output .= '@media ' . $queries[$device] . '{' . $rules . '}';
    }
    return $output !== '' ? '<style id="builder-element-styles">' . $output . '</style>' : '';
}

function builder_responsive_widget_css(array $widgets, string $device, int $depth = 0): string
{
    if ($depth > 8) return '';
    $propertyMap = [
        'font_size' => 'font-size', 'line_height' => 'line-height', 'width' => 'width',
        'max_width' => 'max-width', 'min_height' => 'min-height', 'gap' => 'gap',
        'padding' => 'padding', 'margin' => 'margin', 'text_align' => 'text-align', 'display' => 'display'
    ];
    $rules = '';
    foreach (array_slice($widgets, 0, 40) as $widget) {
        if (!is_array($widget)) continue;
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($widget['id'] ?? ''));
        $responsive = $widget['styles']['responsive'][$device] ?? [];
        if ($id !== '' && is_array($responsive)) {
            $declarations = [];
            foreach ($propertyMap as $field => $property) {
                if (!isset($responsive[$field]) || !is_scalar($responsive[$field])) continue;
                $value = trim((string) $responsive[$field]);
                if ($field === 'text_align') {
                    if (!in_array($value, ['right','left','center','justify','start','end'], true)) continue;
                } elseif ($field === 'display') {
                    if (!in_array($value, ['none','block','flex','grid','inline-flex'], true)) continue;
                } elseif (!preg_match('/^-?[0-9]{1,4}(?:\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)?$/', $value)) {
                    continue;
                }
                $declarations[] = $property . ':' . $value;
            }
            if ($declarations) $rules .= '[data-widget-node="' . $id . '"]{' . implode(';', $declarations) . '}';
        }
        $children = is_array($widget['children'] ?? null) ? $widget['children'] : [];
        if (!$children && is_array($widget['content']['children'] ?? null)) $children = $widget['content']['children'];
        if ($children) $rules .= builder_responsive_widget_css($children, $device, $depth + 1);
    }
    return $rules;
}

function builder_responsive_css(array $blocks): string
{
    $propertyMap = [
        'font_size' => 'font-size', 'line_height' => 'line-height', 'width' => 'width',
        'max_width' => 'max-width', 'min_height' => 'min-height', 'gap' => 'gap',
        'padding' => 'padding', 'margin' => 'margin', 'text_align' => 'text-align', 'display' => 'display'
    ];
    $queries = ['tablet' => '(max-width: 1024px)', 'mobile' => '(max-width: 767px)'];
    $css = '';
    foreach ($queries as $device => $query) {
        $rules = '';
        foreach ($blocks as $block) {
            $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($block['block_key'] ?? ''));
            $responsive = $block['settings']['responsive'][$device] ?? [];
            if ($key === '' || !is_array($responsive)) continue;
            $declarations = [];
            foreach ($propertyMap as $field => $property) {
                if (!isset($responsive[$field]) || !is_scalar($responsive[$field])) continue;
                $value = trim((string) $responsive[$field]);
                if ($field === 'text_align') {
                    if (!in_array($value, ['right','left','center','justify','start','end'], true)) continue;
                } elseif ($field === 'display') {
                    if (!in_array($value, ['none','block','flex','grid','inline-flex'], true)) continue;
                } elseif (!preg_match('/^-?[0-9]{1,4}(?:\\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)?$/', $value)) {
                    continue;
                }
                $declarations[] = $property . ':' . $value;
            }
            if ($declarations) $rules .= '[data-builder-key="' . $key . '"]{' . implode(';', $declarations) . '}';
            $children = is_array($block['content']['children'] ?? null) ? $block['content']['children'] : [];
            if ($children) $rules .= builder_responsive_widget_css($children, $device);
            $principles = is_array($block['content']['principles_items'] ?? null) ? $block['content']['principles_items'] : [];
            $items = is_array($block['content']['items'] ?? null) ? $block['content']['items'] : [];
            if ($principles) $rules .= builder_responsive_widget_css($principles, $device);
            if ($items) $rules .= builder_responsive_widget_css($items, $device);
        }
        if ($rules !== '') $css .= '@media ' . $query . '{' . $rules . '}';
    }
    return $css !== '' ? '<style id="builder-responsive-styles">' . $css . '</style>' : '';
}

function builder_render_custom_block(array $block): string
{
    $type = (string) ($block['block_type'] ?? '');
    $content = is_array($block['content'] ?? null) ? $block['content'] : [];
    $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
    $style = builder_block_style($settings);
    $title = h($content['title'] ?? '');
    $body = nl2br(h($content['body'] ?? ''));
    $key = h($block['block_key'] ?? '');
    $id = 'block-' . preg_replace('/[^a-z0-9_-]/i', '', (string) ($block['block_key'] ?? ''));

    if ($type === 'text') {
        return '<section class="section-shell builder-custom-block builder-text-block" id="' . $id . '" data-builder-key="' . $key . '" data-builder-id="' . (int) $block['id'] . '"' . $style .
            '><div class="container"><div class="section-heading"><h2>' . $title . '</h2><p>' . $body . '</p></div></div></section>';
    }
    if ($type === 'cta') {
        $buttonText = h($content['button_text'] ?? 'تماس با من');
        $url = (string) ($content['button_url'] ?? '#contact');
        $safeUrl = '#contact';
        if (preg_match('/^#[a-zA-Z0-9_-]+$/', $url)) $safeUrl = $url;
        elseif (strpos($url, '/') === 0 && strpos($url, '//') !== 0) $safeUrl = app_url($url);
        elseif (preg_match('#^https://[a-z0-9.-]+(?::[0-9]{1,5})?(?:/.*)?$#i', $url)) $safeUrl = $url;
        return '<section class="section-shell builder-custom-block builder-cta-block" data-builder-key="' . $key . '" data-builder-id="' . (int) $block['id'] . '"' . $style .
            '><div class="container"><div class="panel-card"><h2>' . $title . '</h2><p>' . $body .
            '</p><a class="button button-primary" href="' . h($safeUrl) . '">' . $buttonText . '</a></div></div></section>';
    }
    if ($type === 'stats') {
        $items = isset($content['items']) && is_array($content['items']) ? $content['items'] : [];
        $cards = '';
        foreach (array_slice($items, 0, 6) as $item) {
            if (!is_array($item)) continue;
            $itemId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['id'] ?? ''));
            $itemAttr = $itemId !== '' ? ' data-widget-node="' . $itemId . '"' : '';
            $itemStyle = builder_widget_style(is_array($item['styles'] ?? null) ? $item['styles'] : []);
            $cards .= '<article class="principle-card"' . $itemAttr . $itemStyle . '><span class="principle-icon">' . h($item['value'] ?? '') .
                '</span><h3>' . h($item['label'] ?? '') . '</h3><p>' . h($item['description'] ?? '') . '</p></article>';
        }
        return '<section class="section-shell builder-custom-block builder-stats-block" data-builder-key="' . $key . '" data-builder-id="' . (int) $block['id'] . '"' . $style .
            '><div class="container">' . ($title !== '' ? '<h2>' . $title . '</h2>' : '') .
            '<div class="principles-grid">' . $cards . '</div></div></section>';
    }
    if ($type === 'spacer') {
        $height = max(8, min(240, (int) ($content['height'] ?? 48)));
        return '<div class="builder-spacer" data-builder-key="' . $key . '" data-builder-id="' . (int) $block['id'] . '" aria-hidden="true" style="height:' . $height . 'px"></div>';
    }
    $widgetTypes = ['container','grid','card','heading','paragraph','button','image','divider','icon','counter','list','video','feature','pricing','accordion','testimonial','gallery','timeline','skill','logo_cloud','team','tabs','newsletter','icon_box'];
    if (in_array($type, $widgetTypes, true)) {
        $widget = ['type' => $type, 'content' => $content, 'styles' => $settings,
            'children' => is_array($content['children'] ?? null) ? $content['children'] : []];
        $inner = builder_render_nested_widget($widget);
        $wrapperClass = $type === 'container' || $type === 'grid' ? 'builder-layout-root' : 'builder-widget-root';
        return '<section class="section-shell builder-custom-block ' . $wrapperClass . '" data-builder-key="' . $key .
            '" data-builder-id="' . (int) $block['id'] . '"' . $style . '><div class="container">' . $inner . '</div></section>';
    }
    return '';
}

function builder_apply_fragment_attributes(string $fragment, array $block): string
{
    $key = h($block['block_key'] ?? '');
    $id = (int) ($block['id'] ?? 0);
    $style = builder_block_style(is_array($block['settings'] ?? null) ? $block['settings'] : []);
    return preg_replace_callback('/^<([a-z][a-z0-9:-]*)(\b[^>]*)>/i', static function (array $match) use ($key, $id, $style): string {
        return '<' . $match[1] . $match[2] . ' data-builder-key="' . $key . '" data-builder-id="' . $id . '"' . $style . '>';
    }, $fragment, 1) ?? $fragment;
}

function builder_apply_to_html(string $html, array $blocks, array $design, bool $preview = false): string
{
    $markers = [
        'hero' => '<section class="hero section-shell" id="home">',
        'intro' => '<section class="intro-strip">',
        'projects' => '<section class="work-section section-shell" id="work">',
        'about' => '<section class="about-section section-shell" id="about">',
        'now' => '<section class="now-section section-shell" id="now">',
        'contact' => '<section class="contact-section section-shell" id="contact">'
    ];
    $fragments = [];
    foreach ($markers as $key => $marker) $fragments[$key] = builder_extract_section($html, $marker);
    $footerFragment = builder_extract_footer($html);

    $footerBlock = null;
    $rendered = '';
    foreach ($blocks as $block) {
        $type = (string) ($block['block_type'] ?? '');
        if ($type === 'footer') {
            $footerBlock = $block;
            continue;
        }
        if (empty($block['is_visible']) && !$preview) continue;
        $fragment = $fragments[$type] ?? '';
        if ($fragment !== '') {
            $fragment = builder_apply_fragment_attributes($fragment, $block);
            if ($type === 'about' && isset($block['content']['principles_items']) && is_array($block['content']['principles_items'])) {
                $principleCards = '';
                foreach (array_slice($block['content']['principles_items'], 0, 12) as $item) {
                    if (!is_array($item)) continue;
                    $itemId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['id'] ?? ''));
                    $itemAttr = $itemId !== '' ? ' data-widget-node="' . $itemId . '"' : '';
                    $itemStyle = builder_widget_style(is_array($item['styles'] ?? null) ? $item['styles'] : []);
                    $principleCards .= '<article class="principle-card reveal"' . $itemAttr . $itemStyle .
                        '><span class="principle-icon">' . h($item['number'] ?? '') . '</span><h3>' . h($item['title'] ?? '') .
                        '</h3><p>' . h($item['description'] ?? '') . '</p></article>';
                }
                $fragment = preg_replace_callback('/<div class="principles-grid"[^>]*>.*?<\\/div>/s', static function (array $match) use ($principleCards): string {
                    if (!preg_match('/^<div\\b[^>]*>/', $match[0], $opening)) return $match[0];
                    return $opening[0] . $principleCards . '</div>';
                }, $fragment, 1) ?? $fragment;
            }
            if ($type === 'about' && isset($block['content']['show_principles']) &&
                !$block['content']['show_principles']) {
                $fragment = preg_replace('/<div class="principles-grid">/', '<div class="principles-grid" hidden>', $fragment, 1) ?? $fragment;
            }
        } else {
            $fragment = builder_render_custom_block($block);
        }
        if ($preview && empty($block['is_visible'])) {
            $fragment = preg_replace('/^<([a-z][a-z0-9:-]*)(\b[^>]*)>/i', '<$1$2 hidden>', $fragment, 1) ?? $fragment;
        }
        $rendered .= $fragment . "\n";
    }

    $main = strpos($html, '<main id="main">');
    if ($main !== false) {
        $contentStart = strpos($html, '>', $main) + 1;
        $contentEnd = strrpos($html, '</main>');
        if ($contentStart > 0 && $contentEnd !== false && $contentEnd >= $contentStart) {
            $html = substr($html, 0, $contentStart) . "\n" . $rendered . "\n" . substr($html, $contentEnd);
        }
    }

    if ($footerFragment !== '' && ($footerBlock === null || !empty($footerBlock['is_visible']) || $preview)) {
        $footerHtml = $footerBlock === null ? $footerFragment : builder_apply_fragment_attributes($footerFragment, $footerBlock);
        if ($preview && $footerBlock !== null && empty($footerBlock['is_visible'])) {
            $footerHtml = preg_replace('/^<footer\b([^>]*)>/i', '<footer$1 hidden>', $footerHtml, 1) ?? $footerHtml;
        }
        $bodyEnd = stripos($html, '</body>');
        if ($bodyEnd !== false) $html = substr($html, 0, $bodyEnd) . $footerHtml . "\n" . substr($html, $bodyEnd);
    }

    $css = builder_design_css($design) . builder_responsive_css($blocks) . builder_legacy_element_css($blocks);
    $headEnd = stripos($html, '</head>');
    if ($headEnd !== false) $html = substr($html, 0, $headEnd) . $css . substr($html, $headEnd);
    return $html;
}

function builder_preview_script(): string
{
    return <<<'HTML'
<style id="builder-live-preview-style">
html[data-builder-preview="1"] [data-builder-key],html[data-builder-preview="1"] [data-widget-node]{cursor:pointer;outline:1px dashed transparent;outline-offset:3px;transition:outline-color .12s,box-shadow .12s}
html[data-builder-preview="1"] [data-widget-node]:hover{outline-color:rgba(139,120,255,.9);box-shadow:0 0 0 4px rgba(139,120,255,.08)}
html[data-builder-preview="1"] [data-widget-node].builder-live-node-selected{outline:2px solid #59d5c9!important;outline-offset:3px!important;box-shadow:0 0 0 5px rgba(89,213,201,.14)!important}
html[data-builder-preview="1"] [data-builder-key]:hover{outline-color:rgba(139,120,255,.9);box-shadow:0 0 0 4px rgba(139,120,255,.08)}
html[data-builder-preview="1"] [data-builder-key].builder-live-selected{outline:2px solid #9c83ff!important;outline-offset:3px!important;box-shadow:0 0 0 5px rgba(156,131,255,.13)!important}
html[data-builder-preview="1"] [data-builder-edit-field]{cursor:text}
html[data-builder-preview="1"] [data-builder-edit-field]:hover{outline:1px dashed rgba(156,131,255,.8);outline-offset:2px}
html[data-builder-preview="1"] .builder-inline-editing{outline:2px solid #9c83ff!important;outline-offset:3px;background:rgba(156,131,255,.07);cursor:text}
html[data-builder-preview="1"] body{padding-top:0!important}
</style>
<script>
(function(){
  'use strict';
  if (window.parent === window) return;
  document.documentElement.dataset.builderPreview = '1';
  var selectedKey = null, selectedNodeId = '', selectedLegacyField = '';
  function sendToParent(message){
    if (window.parent !== window) window.parent.postMessage(message,'*');
  }
  function safeHref(value){
    var raw = String(value || '');
    if (/^#[a-zA-Z0-9_-]+$/.test(raw)) return raw;
    if (raw.charAt(0) === '/' && raw.charAt(1) !== '/') return raw;
    try { var u = new URL(raw, location.href); return u.protocol === 'https:' ? raw : '#'; } catch(e){ return '#'; }
  }
  function setText(root, selector, value){
    var node = root.querySelector(selector);
    if (node && value !== undefined && value !== null) node.textContent = String(value);
  }
  function setSplit(root, selector, first, second){
    var node = root.querySelector(selector);
    if (!node) return;
    node.replaceChildren(document.createTextNode(String(first || '')), document.createElement('br'));
    var span = document.createElement('span');
    span.className = 'type-gradient';
    span.textContent = String(second || '');
    node.appendChild(span);
  }
  function card(tag, className, value){
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined) node.textContent = String(value);
    return node;
  }

  function makeNestedWidget(item,depth){
    if (!item || typeof item !== 'object' || depth > 8) return document.createTextNode('');
    var type = String(item.type || 'paragraph'), c = item.content || item, node;
    var allowed = ['container','grid','card','heading','paragraph','button','image','divider','icon','counter','list','video','feature','pricing','accordion','testimonial','gallery','timeline','skill','logo_cloud','team','tabs','newsletter','icon_box'];
    if (allowed.indexOf(type) === -1) return document.createTextNode('');
    if (type === 'container') {
      node = card('div','builder-layout-container is-' + (c.direction === 'row' ? 'row' : 'column'));
    } else if (type === 'grid') {
      node = card('div','builder-layout-grid');
      node.style.setProperty('--builder-grid-columns',String(Math.max(1,Math.min(6,Number(c.columns)||2))));
    } else if (type === 'card') {
      node = card('article','builder-widget-card');
      var cardContent = card('div','builder-widget-card-content');
      cardContent.append(card('h3','',c.title || 'عنوان کارت'),card('p','',c.body || 'توضیحات کارت'));
      node.appendChild(cardContent);
    } else if (type === 'heading') {
      var level = Math.max(1,Math.min(6,Number(c.level)||2));
      node = card('h' + level,'builder-widget-heading',c.text || 'عنوان جدید');
    } else if (type === 'paragraph') {
      node = card('p','builder-widget-paragraph',c.text || 'متن جدید');
    } else if (type === 'button') {
      node = card('a','button button-primary builder-widget-button',c.text || 'دکمه جدید');
      node.href = safeHref(c.url || '#contact');
    } else if (type === 'image') {
      node = card('figure','builder-widget-image');
      if (c.src) {
        var img = card('img','');
        img.src = safeHref(c.src);
        img.alt = String(c.alt || '');
        img.loading = 'lazy';
        img.style.width = '100%';
        node.appendChild(img);
      } else node = card('div','builder-widget-image-placeholder','تصویر انتخاب نشده است');
    } else if (type === 'divider') {
      node = card('hr','builder-widget-divider');
    } else if (type === 'icon') {
      node = card('span','builder-widget-icon',c.symbol || '✦');
    } else if (type === 'counter') {
      node = card('div','builder-widget-counter');
      node.append(card('strong','',c.value || '0'),card('span','',c.label || 'عنوان آمار'));
    } else if (type === 'list') {
      node = card('ul','builder-widget-list');
      (Array.isArray(c.items) ? c.items : []).slice(0,30).forEach(function(entry){
        node.appendChild(card('li','',typeof entry === 'object' ? entry.text || '' : entry));
      });
    } else if (type === 'feature' || type === 'icon_box') {
      node = card('article','builder-widget-feature');
      node.append(card('span','builder-feature-icon',c.symbol || '✦'),card('h3','',c.title || 'ویژگی جدید'),card('p','',c.description || 'توضیحات ویژگی'));
    } else if (type === 'pricing') {
      node = card('article','builder-widget-pricing');
      node.append(card('h3','',c.title || 'پلن'),card('strong','builder-pricing-price',c.price || ''),card('small','',c.period || ''),card('p','',c.description || ''));
      var priceList=card('ul','');(Array.isArray(c.items)?c.items:[]).forEach(function(entry){priceList.appendChild(card('li','',typeof entry==='object'?entry.text||entry.title||'':entry));});
      node.appendChild(priceList);var priceButton=card('a','button button-primary',c.button_text||'انتخاب پلن');priceButton.href=safeHref(c.button_url||'#contact');node.appendChild(priceButton);
    } else if (type === 'accordion') {
      node=card('div','builder-widget-accordion');(Array.isArray(c.items)?c.items:[]).forEach(function(entry){var d=card('details','builder-widget-faq');d.append(card('summary','',entry.question||entry.title||''),card('p','',entry.answer||entry.description||''));node.appendChild(d);});
    } else if (type === 'testimonial') {
      node=card('figure','builder-widget-testimonial');node.append(card('blockquote','',c.quote||'نظر مشتری'),card('strong','',c.author||'نام مشتری'),card('span','',c.role||''));
    } else if (type === 'gallery') {
      node=card('section','builder-widget-gallery');if(c.title)node.appendChild(card('h3','',c.title));(Array.isArray(c.items)?c.items:[]).forEach(function(entry){var f=card('figure','');if(entry.src){var im=card('img','');im.src=safeHref(entry.src);im.alt=entry.alt||'';f.appendChild(im);}f.appendChild(card('figcaption','',entry.title||entry.alt||'تصویر'));node.appendChild(f);});
    } else if (type === 'timeline') {
      node=card('div','builder-widget-timeline');if(c.title)node.appendChild(card('h3','',c.title));(Array.isArray(c.items)?c.items:[]).forEach(function(entry){var ev=card('article','');ev.append(card('time','',entry.date||''),card('h4','',entry.title||''),card('p','',entry.description||''));node.appendChild(ev);});
    } else if (type === 'skill') {
      node=card('div','builder-widget-skill');var sh=card('div','');sh.append(card('strong','',c.title||'مهارت'),card('span','',String(Math.max(0,Math.min(100,Number(c.value)||0)))+'%'));var tr=card('i',''),bar=card('b','');bar.style.width=Math.max(0,Math.min(100,Number(c.value)||0))+'%';tr.appendChild(bar);node.append(sh,tr);
    } else if (type === 'logo_cloud' || type === 'team' || type === 'tabs') {
      node=card('section',type==='logo_cloud'?'builder-widget-logos':(type==='team'?'builder-widget-team':'builder-widget-tabs'));if(c.title)node.appendChild(card('h3','',c.title));
      (Array.isArray(c.items)?c.items:[]).forEach(function(entry){var tile=card('article',type==='team'?'builder-team-card':(type==='tabs'?'builder-tab-card':'builder-logo-item'));if(type==='logo_cloud')tile.appendChild(card('strong','',entry.name||'Brand'));else if(type==='team')tile.append(card('h4','',entry.name||''),card('p','',entry.role||''));else tile.append(card('h4','',entry.title||''),card('p','',entry.description||''));node.appendChild(tile);});
    } else if (type === 'newsletter') {
      node=card('section','builder-widget-newsletter');node.append(card('h3','',c.title||'عضویت در خبرنامه'),card('p','',c.description||''));var nf=card('div','builder-newsletter-form');nf.append(card('span','',c.placeholder||'ایمیل شما'),card('b','',c.button_text||'عضویت'));node.appendChild(nf);
    } else if (type === 'video') {
      node = card('div','builder-widget-video');
      var videoUrl = String(c.url || '');
      if (/^https:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_-]+/.test(videoUrl) || /^https:\/\/player\.vimeo\.com\/video\/[0-9]+/.test(videoUrl)) {
        var iframe = card('iframe','');
        iframe.src = videoUrl;
        iframe.title = String(c.title || 'ویدیو');
        iframe.loading = 'lazy';
        iframe.allowFullscreen = true;
        node.appendChild(iframe);
      } else node = card('div','builder-widget-image-placeholder','آدرس ویدیو معتبر نیست');
    }
    if (item.id) node.setAttribute('data-widget-node',String(item.id));
    var styles = item.styles || {};
    var styleMap = {background:'backgroundColor',color:'color',text_color:'color',font_size:'fontSize',font_weight:'fontWeight',line_height:'lineHeight',text_align:'textAlign',width:'width',max_width:'maxWidth',min_height:'minHeight',gap:'gap',padding:'padding',margin:'margin',radius:'borderRadius',border:'border',shadow:'boxShadow',display:'display'};
    Object.keys(styleMap).forEach(function(key){
      if (styles[key] !== undefined && styles[key] !== '') node.style[styleMap[key]] = String(styles[key]);
    });
    (Array.isArray(item.children) ? item.children : []).slice(0,40).forEach(function(child){
      node.appendChild(makeNestedWidget(child,depth+1));
    });
    return node;
  }

  function makeCustom(block){
    var c = block.content || {}, node, container, title, body;
    if (block.block_type === 'spacer') {
      node = card('div','builder-spacer');
      node.setAttribute('aria-hidden','true');
      return node;
    }
    if (['container','grid','card','heading','paragraph','button','image','divider','icon','counter','list','video','feature','pricing','accordion','testimonial','gallery','timeline','skill','logo_cloud','team','tabs','newsletter','icon_box'].indexOf(block.block_type) !== -1) {
      node = card('section','section-shell builder-custom-block builder-widget-root builder-' + block.block_type + '-block');
      container = card('div','container');
      container.appendChild(makeNestedWidget({type:block.block_type,content:c,styles:block.settings || {},children:c.children || []},0));
      node.appendChild(container);
      return node;
    }
    node = card('section','section-shell builder-custom-block builder-' + block.block_type + '-block');
    container = card('div','container');
    title = card('h2','',c.title || '');
    body = card('p','',c.body || '');
    if (block.block_type === 'text') {
      var heading = card('div','section-heading');
      heading.append(title,body); container.appendChild(heading);
    } else if (block.block_type === 'cta') {
      var panel = card('div','panel-card');
      var link = card('a','button button-primary',c.button_text || 'تماس با من');
      link.href = safeHref(c.button_url || '#contact');
      panel.append(title,body,link); container.appendChild(panel);
    } else if (block.block_type === 'stats') {
      var grid = card('div','principles-grid');
      (Array.isArray(c.items) ? c.items : []).slice(0,6).forEach(function(item){
        if (!item || typeof item !== 'object') return;
        var tile = card('article','principle-card');
        tile.append(card('span','principle-icon',item.value || ''),card('h3','',item.label || ''),card('p','',item.description || ''));
        grid.appendChild(tile);
      });
      if (c.title) container.appendChild(title);
      container.appendChild(grid);
    }
    node.appendChild(container);
    return node;
  }
  function markEditable(node,type){
    var maps = {
      hero:{eyebrow:'.hero-copy .eyebrow',title_line1:'.hero-copy h1',title_line2:'.hero-copy h1 .type-gradient',description:'.hero-description',
        primary_cta_text:'.hero-actions a:first-child',secondary_cta_text:'.hero-actions a:nth-child(2)',footnote:'.hero-footnote span:last-child'},
      intro:{text_before:'.intro-strip-inner p',text_emphasis:'.intro-strip-inner p span'},
      projects:{heading_before:'.section-heading h2',heading_emphasis:'.section-heading h2 span',description:'.section-heading > p'},
      about:{section_index:'.about-heading .section-index',title_line1:'.about-heading h2',title_line2:'.about-heading h2 .type-gradient',description:'.about-heading > p',link_text:'.about-heading .text-link'},
      now:{section_index:'.now-panel-copy .section-index',title_line1:'.now-panel-copy h2',title_line2:'.now-panel-copy h2 .type-gradient',description:'.now-panel-copy > p',status_title:'.now-note strong',status_subtitle:'.now-note small'},
      contact:{section_index:'.contact-copy .section-index',title_line1:'.contact-copy h2',title_line2:'.contact-copy h2 .type-gradient',description:'.contact-copy > p'},
      footer:{tagline:'.footer-brand > span:last-child',copyright:'.footer-copy'},
      text:{title:'h2',body:'p'},cta:{title:'h2',body:'p',button_text:'a.button'},stats:{title:'h2'},
      heading:{text:'h1,h2,h3,h4,h5,h6'},paragraph:{text:'p'},button:{text:'a.button'},
      card:{title:'h3',body:'p'},counter:{value:'strong',label:'span'},image:{alt:'img'},icon:{symbol:'.builder-widget-icon'}
    };
    var fields = maps[type] || {};
    Object.keys(fields).forEach(function(field){
      var target = node.querySelector(fields[field]);
      if (target) {
        target.setAttribute('data-builder-edit-field',field);
        target.setAttribute('data-widget-node','legacy-' + field);
      }
    });
  }
  function applyLegacyElementStyles(node,block){
    var maps = {
      hero:{eyebrow:'.hero-copy .eyebrow',title_line1:'.hero-copy h1',title_line2:'.hero-copy h1 .type-gradient',description:'.hero-description',
        primary_cta_text:'.hero-actions a:first-child',secondary_cta_text:'.hero-actions a:nth-child(2)',footnote:'.hero-footnote span:last-child'},
      intro:{text_before:'.intro-strip-inner p',text_emphasis:'.intro-strip-inner p span'},
      projects:{heading_before:'.section-heading h2',heading_emphasis:'.section-heading h2 span',description:'.section-heading > p'},
      about:{section_index:'.about-heading .section-index',title_line1:'.about-heading h2',title_line2:'.about-heading h2 .type-gradient',description:'.about-heading > p',link_text:'.about-heading .text-link'},
      now:{section_index:'.now-panel-copy .section-index',title_line1:'.now-panel-copy h2',title_line2:'.now-panel-copy h2 .type-gradient',description:'.now-panel-copy > p',status_title:'.now-note strong',status_subtitle:'.now-note small'},
      contact:{section_index:'.contact-copy .section-index',title_line1:'.contact-copy h2',title_line2:'.contact-copy h2 .type-gradient',description:'.contact-copy > p'},
      footer:{tagline:'.footer-brand > span:last-child',copyright:'.footer-copy'},
      text:{title:'h2',body:'p'},cta:{title:'h2',body:'p',button_text:'a.button'},stats:{title:'h2'}
    };
    var elementStyles = block.content && block.content.element_styles || {};
    var styleMap = {background:'backgroundColor',text_color:'color',font_size:'fontSize',font_weight:'fontWeight',
      line_height:'lineHeight',text_align:'textAlign',width:'width',max_width:'maxWidth',min_height:'minHeight',
      gap:'gap',padding:'padding',margin:'margin',radius:'borderRadius',border:'border',shadow:'boxShadow',display:'display'};
    var fields = maps[block.block_type] || {};
    Object.keys(fields).forEach(function(field){
      var target = node.querySelector(fields[field]), styles = elementStyles[field] || {};
      if (!target) return;
      Object.keys(styleMap).forEach(function(key){
        if (styles[key] !== undefined && styles[key] !== '') target.style[styleMap[key]] = String(styles[key]);
        else target.style[styleMap[key]] = '';
      });
    });
  }

  function updateContent(node, block){
    var c = block.content || {};
    var type = block.block_type;
    if (type === 'hero') {
      var eyebrow = node.querySelector('.hero-copy .eyebrow');
      if (eyebrow) {
        var dot = eyebrow.querySelector('.status-dot');
        if (dot) eyebrow.replaceChildren(dot,document.createTextNode(' ' + String(c.eyebrow || '')));
        else eyebrow.textContent = String(c.eyebrow || '');
      }
      setSplit(node,'.hero-copy h1',c.title_line1,c.title_line2);
      setText(node,'.hero-description',c.description);
      var links = node.querySelectorAll('.hero-actions a');
      if (links[0]) {
        var arrow = links[0].querySelector('span[aria-hidden="true"]');
        if (arrow) links[0].replaceChildren(document.createTextNode(String(c.primary_cta_text || '') + ' '),arrow);
        else links[0].textContent = String(c.primary_cta_text || '');
        links[0].href = safeHref(c.primary_cta_url || '#work');
      }
      if (links[1]) { links[1].textContent = String(c.secondary_cta_text || ''); links[1].href = safeHref(c.secondary_cta_url || '#contact'); }
      setText(node,'.hero-footnote span:last-child',c.footnote);
    } else if (type === 'intro') {
      var p = node.querySelector('.intro-strip-inner p');
      if (p) {
        p.replaceChildren(document.createTextNode(String(c.text_before || '') + ' '));
        p.appendChild(card('span','',c.text_emphasis || ''));
      }
    } else if (type === 'projects') {
      var heading = node.querySelector('.section-heading h2');
      if (heading) {
        heading.replaceChildren(document.createTextNode(String(c.heading_before || '') + ' '));
        heading.appendChild(card('span','type-gradient',c.heading_emphasis || ''));
      }
      setText(node,'.section-heading > p',c.description);
    } else if (type === 'about') {
      setText(node,'.about-heading .section-index',c.section_index);
      setSplit(node,'.about-heading h2',c.title_line1,c.title_line2);
      setText(node,'.about-heading > p',c.description);
      setText(node,'.about-heading .text-link',c.link_text);
      var aboutLink = node.querySelector('.about-heading .text-link');
      if (aboutLink && c.link_url) aboutLink.href = safeHref(c.link_url);
      var grid = node.querySelector('.principles-grid');
      if (grid && Array.isArray(c.principles_items)) {
        grid.replaceChildren();
        c.principles_items.slice(0,12).forEach(function(item){
          if (!item || typeof item !== 'object') return;
          var tile = card('article','principle-card reveal');
          if (item.id) tile.setAttribute('data-widget-node',String(item.id));
          var itemStyleMap = {background:'backgroundColor',text_color:'color',font_size:'fontSize',font_weight:'fontWeight',line_height:'lineHeight',text_align:'textAlign',width:'width',max_width:'maxWidth',min_height:'minHeight',gap:'gap',padding:'padding',margin:'margin',radius:'borderRadius',border:'border',shadow:'boxShadow',display:'display'};
          Object.keys(itemStyleMap).forEach(function(key){ tile.style[itemStyleMap[key]] = item.styles && item.styles[key] !== undefined ? String(item.styles[key]) : ''; });
          tile.append(card('span','principle-icon',item.number || ''),card('h3','',item.title || ''),card('p','',item.description || ''));
          grid.appendChild(tile);
        });
      }
      if (grid) grid.hidden = c.show_principles === false || c.show_principles === 0 || c.show_principles === '0';
    } else if (type === 'now') {
      setText(node,'.now-panel-copy .section-index',c.section_index);
      setSplit(node,'.now-panel-copy h2',c.title_line1,c.title_line2);
      setText(node,'.now-panel-copy > p',c.description);
      setText(node,'.now-note strong',c.status_title);
      setText(node,'.now-note small',c.status_subtitle);
    } else if (type === 'contact') {
      setText(node,'.contact-copy .section-index',c.section_index);
      setSplit(node,'.contact-copy h2',c.title_line1,c.title_line2);
      setText(node,'.contact-copy > p',c.description);
    } else if (type === 'footer') {
      setText(node,'.footer-brand > span:last-child',c.tagline);
      var copy = node.querySelector('.footer-copy');
      if (copy) copy.textContent = '© ' + new Date().getFullYear() + ' میلاد · ' + String(c.copyright || '');
    } else if (type === 'text' || type === 'cta' || type === 'stats') {
      var h = node.querySelector('h2');
      var p2 = node.querySelector('p');
      if (h) h.textContent = String(c.title || '');
      if (p2) p2.textContent = String(c.body || '');
      if (type === 'cta') {
        var a = node.querySelector('a.button');
        if (a) { a.textContent = String(c.button_text || 'تماس با من'); a.href = safeHref(c.button_url || '#contact'); }
      }
      if (type === 'stats') {
        var g = node.querySelector('.principles-grid');
        if (g) {
          g.replaceChildren();
          (Array.isArray(c.items) ? c.items : []).slice(0,6).forEach(function(item){
            if (!item || typeof item !== 'object') return;
            var tile = card('article','principle-card');
            if (item.id) tile.setAttribute('data-widget-node',String(item.id));
            var itemStyleMap = {background:'backgroundColor',text_color:'color',font_size:'fontSize',font_weight:'fontWeight',line_height:'lineHeight',text_align:'textAlign',width:'width',max_width:'maxWidth',min_height:'minHeight',gap:'gap',padding:'padding',margin:'margin',radius:'borderRadius',border:'border',shadow:'boxShadow',display:'display'};
            Object.keys(itemStyleMap).forEach(function(key){ tile.style[itemStyleMap[key]] = item.styles && item.styles[key] !== undefined ? String(item.styles[key]) : ''; });
            tile.append(card('span','principle-icon',item.value || ''),card('h3','',item.label || ''),card('p','',item.description || ''));
            g.appendChild(tile);
          });
        }
      }
    } else if (['container','grid','card','heading','paragraph','button','image','divider','icon','counter','list','video'].indexOf(type) !== -1) {
      var rebuilt = makeCustom(block);
      node.className = rebuilt.className;
      node.replaceChildren.apply(node,Array.prototype.slice.call(rebuilt.childNodes));
    } else if (type === 'spacer') {
      node.style.height = Math.max(8,Math.min(240,Number(c.height) || 48)) + 'px';
    }
  }

  function applyResponsiveStyles(blocks){
    var style = document.getElementById('builder-responsive-styles');
    if (!style) {
      style = document.createElement('style');
      style.id = 'builder-responsive-styles';
      document.head.appendChild(style);
    }
    var properties = {font_size:'font-size',line_height:'line-height',width:'width',max_width:'max-width',min_height:'min-height',gap:'gap',padding:'padding',margin:'margin',text_align:'text-align',display:'display'};
    var media = {tablet:'(max-width: 1024px)',mobile:'(max-width: 767px)'};
    function widgetRules(items,device,depth) {
      if (depth > 8) return '';
      var rules = '';
      (Array.isArray(items) ? items : []).slice(0,40).forEach(function(item){
        if (!item || typeof item !== 'object') return;
        var id = String(item.id || '').replace(/[^a-zA-Z0-9_-]/g,'');
        var responsive = item.styles && item.styles.responsive && item.styles.responsive[device];
        if (id && responsive && typeof responsive === 'object') {
          var declarations = [];
          Object.keys(properties).forEach(function(field){
            if (responsive[field] === undefined) return;
            var value = String(responsive[field]).trim();
            if (field === 'text_align') {
              if (['right','left','center','justify','start','end'].indexOf(value) === -1) return;
            } else if (field === 'display') {
              if (['none','block','flex','grid','inline-flex'].indexOf(value) === -1) return;
            } else if (!/^-?[0-9]{1,4}(?:\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)?$/.test(value)) return;
            declarations.push(properties[field] + ':' + value);
          });
          if (declarations.length) rules += '[data-widget-node="' + id + '"]{' + declarations.join(';') + '}';
        }
        rules += widgetRules(item.children || (item.content && item.content.children),device,depth + 1);
      });
      return rules;
    }
    var output = '';
    Object.keys(media).forEach(function(device){
      var rules = '';
      blocks.forEach(function(block){
        var key = String(block.block_key || '').replace(/[^a-zA-Z0-9_-]/g,'');
        var responsive = block.settings && block.settings.responsive && block.settings.responsive[device];
        if (key && responsive && typeof responsive === 'object') {
          var declarations = [];
          Object.keys(properties).forEach(function(field){
            if (responsive[field] === undefined) return;
            var value = String(responsive[field]).trim();
            if (field === 'text_align') {
              if (['right','left','center','justify','start','end'].indexOf(value) === -1) return;
            } else if (field === 'display') {
              if (['none','block','flex','grid','inline-flex'].indexOf(value) === -1) return;
            } else if (!/^-?[0-9]{1,4}(?:\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)?$/.test(value)) return;
            declarations.push(properties[field] + ':' + value);
          });
          if (declarations.length) rules += '[data-builder-key="' + key + '"]{' + declarations.join(';') + '}';
        }
        var elementStyles = block.content && block.content.element_styles || {};
        Object.keys(elementStyles).forEach(function(field){
          var legacy = elementStyles[field] && elementStyles[field].responsive && elementStyles[field].responsive[device];
          if (!legacy || typeof legacy !== 'object') return;
          var declarations = [];
          Object.keys(properties).forEach(function(property){
            if (legacy[property] === undefined) return;
            var value = String(legacy[property]).trim();
            if (property === 'text_align') {
              if (['right','left','center','justify','start','end'].indexOf(value) === -1) return;
            } else if (property === 'display') {
              if (['none','block','flex','grid','inline-flex'].indexOf(value) === -1) return;
            } else if (!/^-?[0-9]{1,4}(?:\.[0-9]{1,2})?(?:px|%|rem|em|vh|vw|fr)?$/.test(value)) return;
            declarations.push(properties[property] + ':' + value);
          });
          if (declarations.length) rules += '[data-builder-key="' + key + '"] [data-widget-node="legacy-' + String(field).replace(/[^a-zA-Z0-9_-]/g,'') + '"]{' + declarations.join(';') + '}';
        });
        rules += widgetRules(block.content && block.content.children,device,0);
        rules += widgetRules(block.content && block.content.principles_items,device,0);
        rules += widgetRules(block.content && block.content.items,device,0);
      });
      if (rules) output += '@media ' + media[device] + '{' + rules + '}';
    });
    style.textContent = output;
  }

  function applyState(data){
    if (!data || !Array.isArray(data.blocks)) return;
    var main = document.querySelector('#main');
    if (!main) return;
    var footerNode = document.querySelector('footer.site-footer');
    var activeKeys = new Set(data.blocks.map(function(block){ return String(block.block_key); }));
    document.querySelectorAll('[data-builder-key]').forEach(function(node){
      if (!activeKeys.has(String(node.dataset.builderKey))) node.hidden = true;
    });
    data.blocks.forEach(function(block){
      if (block.block_type === 'footer') return;
      var node = Array.prototype.find.call(document.querySelectorAll('[data-builder-key]'),function(el){ return el.dataset.builderKey === block.block_key; });
      if (!node && ['text','cta','stats','spacer','container','grid','card','heading','paragraph','button','image','divider','icon','counter','list','video'].indexOf(block.block_type) !== -1) {
        node = makeCustom(block);
        node.dataset.builderKey = block.block_key;
      }
      if (!node) return;
      node.dataset.builderId = String(block.id);
      node.hidden = !block.is_visible;
      node.style.backgroundColor = block.settings && block.settings.background || '';
      node.style.color = block.settings && block.settings.text_color || '';
      node.style.paddingBlock = block.settings && block.settings.padding !== undefined ? Number(block.settings.padding) + 'px' : '';
      node.style.borderRadius = block.settings && block.settings.radius !== undefined ? Number(block.settings.radius) + 'px' : '';
      var rootStyleMap = {font_size:'fontSize',font_weight:'fontWeight',line_height:'lineHeight',text_align:'textAlign',width:'width',max_width:'maxWidth',min_height:'minHeight',gap:'gap',border:'border',shadow:'boxShadow',display:'display'};
      Object.keys(rootStyleMap).forEach(function(key){
        if (block.settings && block.settings[key] !== undefined) node.style[rootStyleMap[key]] = String(block.settings[key]);
        else node.style[rootStyleMap[key]] = '';
      });
      updateContent(node,block);
      markEditable(node,block.block_type);
      applyLegacyElementStyles(node,block);
      main.appendChild(node);
    });
    var footerBlock = data.blocks.find(function(block){ return block.block_type === 'footer'; });
    if (footerNode && footerBlock) {
      footerNode.dataset.builderKey = footerBlock.block_key;
      footerNode.dataset.builderId = String(footerBlock.id);
      footerNode.hidden = !footerBlock.is_visible;
      updateContent(footerNode,footerBlock);
      markEditable(footerNode,'footer');
      applyLegacyElementStyles(footerNode,footerBlock);
    } else if (footerNode) {
      footerNode.hidden = true;
    }
    applyResponsiveStyles(data.blocks);
    var d = data.design || {};
    document.documentElement.style.setProperty('--accent',d.accent_color || '#a58aff');
    document.documentElement.style.setProperty('--accent-strong',d.accent_color || '#a58aff');
    document.documentElement.style.setProperty('--bg',d.page_background || '#080b12');
    document.documentElement.style.setProperty('--container',Math.max(760,Math.min(1600,Number(d.content_width) || 1160)) + 'px');
    document.documentElement.style.setProperty('--radius-lg',Math.max(0,Math.min(48,Number(d.corner_radius) || 0)) + 'px');
    document.documentElement.style.setProperty('--radius-md',Math.max(0,Math.min(48,Number(d.corner_radius) || 0)) + 'px');
    document.documentElement.style.setProperty('--builder-font-scale',d.font_scale || '1');
    document.documentElement.style.setProperty('--builder-section-gap',Math.max(32,Math.min(180,Number(d.section_spacing) || 72)) + 'px');
    if (footerNode && footerBlock) main.after(footerNode);
    if (selectedKey) selectOutline(selectedKey,selectedNodeId,selectedLegacyField);
  }
  function selectOutline(key,nodeId,legacyField){
    selectedKey = key;
    selectedNodeId = String(nodeId || '');
    selectedLegacyField = String(legacyField || '');
    document.querySelectorAll('.builder-live-selected,.builder-live-node-selected').forEach(function(node){
      node.classList.remove('builder-live-selected','builder-live-node-selected');
    });
    var selected = null;
    if (selectedLegacyField) {
      selected = Array.prototype.find.call(document.querySelectorAll('[data-builder-edit-field]'),function(el){
        var owner = el.closest('[data-builder-key]');
        return owner && owner.dataset.builderKey === key && el.dataset.builderEditField === selectedLegacyField;
      });
    } else if (selectedNodeId) {
      selected = Array.prototype.find.call(document.querySelectorAll('[data-widget-node]'),function(el){
        var owner = el.closest('[data-builder-key]');
        return owner && owner.dataset.builderKey === key && el.dataset.widgetNode === selectedNodeId;
      });
    }
    if (selected) selected.classList.add('builder-live-node-selected');
    else {
      selected = Array.prototype.find.call(document.querySelectorAll('[data-builder-key]'),function(el){ return el.dataset.builderKey === key; });
      if (selected) selected.classList.add('builder-live-selected');
    }
  }
  document.addEventListener('dblclick',function(event){
    var target = event.target.closest('[data-builder-edit-field]');
    if (!target) return;
    event.preventDefault();
    event.stopPropagation();
    target.setAttribute('contenteditable','true');
    target.setAttribute('spellcheck','true');
    target.setAttribute('role','textbox');
    target.classList.add('builder-inline-editing');
    target.focus();
  },true);
  document.addEventListener('input',function(event){
    var target = event.target.closest('[contenteditable="true"][data-builder-edit-field]');
    if (!target) return;
    var owner = target.closest('[data-builder-key]');
    if (!owner) return;
    sendToParent({
      type:'milad-builder-inline-edit',
      key:owner.dataset.builderKey,
      field:target.dataset.builderEditField,
      value:target.textContent
    });
  },true);
  document.addEventListener('focusout',function(event){
    var target = event.target.closest('[contenteditable="true"][data-builder-edit-field]');
    if (!target) return;
    var owner = target.closest('[data-builder-key]');
    target.removeAttribute('contenteditable');
    target.classList.remove('builder-inline-editing');
    if (!owner) return;
    sendToParent({
      type:'milad-builder-inline-edit',
      key:owner.dataset.builderKey,
      field:target.dataset.builderEditField,
      value:target.textContent
    });
  },true);
  document.addEventListener('click',function(event){
    var block = event.target.closest('[data-builder-key]');
    if (!block) return;
    if (event.target.closest('a')) event.preventDefault();
    event.stopPropagation();
    var fieldNode = event.target.closest('[data-builder-edit-field]');
    if (fieldNode && block.contains(fieldNode)) {
      sendToParent({
        type:'milad-builder-node-select',key:block.dataset.builderKey,
        legacyField:fieldNode.dataset.builderEditField
      });
      return;
    }
    var widgetNode = event.target.closest('[data-widget-node]');
    if (widgetNode && block.contains(widgetNode)) {
      sendToParent({
        type:'milad-builder-node-select',key:block.dataset.builderKey,
        nodeId:widgetNode.dataset.widgetNode
      });
      return;
    }
    sendToParent({type:'milad-builder-select',key:block.dataset.builderKey});
  },true);
  var resizeOverlay = null;
  var resizeTarget = null;
  var resizeBlock = null;
  var resizeNodeId = '';
  var resizeObserver = null;
  var resizeFrame = 0;

  function ensureResizeOverlay(){
    if (resizeOverlay) return resizeOverlay;
    var style = document.createElement('style');
    style.id = 'builder-direct-resize-style';
    style.textContent = '.builder-direct-resize{position:fixed;z-index:2147483000;border:2px solid #8b6cff;pointer-events:none;box-sizing:border-box;outline:1px solid rgba(139,108,255,.15)}.builder-direct-resize i{position:absolute;width:11px;height:11px;background:#fff;border:2px solid #8b6cff;border-radius:3px;pointer-events:auto;box-sizing:border-box;touch-action:none}.builder-direct-resize .tl{left:-7px;top:-7px;cursor:nwse-resize}.builder-direct-resize .t{left:50%;top:-7px;transform:translateX(-50%);cursor:ns-resize}.builder-direct-resize .tr{right:-7px;top:-7px;cursor:nesw-resize}.builder-direct-resize .r{right:-7px;top:50%;transform:translateY(-50%);cursor:ew-resize}.builder-direct-resize .br{right:-7px;bottom:-7px;cursor:nwse-resize}.builder-direct-resize .b{left:50%;bottom:-7px;transform:translateX(-50%);cursor:ns-resize}.builder-direct-resize .bl{left:-7px;bottom:-7px;cursor:nesw-resize}.builder-direct-resize .l{left:-7px;top:50%;transform:translateY(-50%);cursor:ew-resize}.builder-direct-resize-label{position:absolute;top:-31px;left:0;background:#171225;color:#fff;padding:5px 8px;border:1px solid rgba(255,255,255,.12);border-radius:7px;font:12px/1.2 sans-serif;white-space:nowrap;box-shadow:0 5px 18px rgba(0,0,0,.2)}';
    document.head.appendChild(style);
    resizeOverlay = document.createElement('div');
    resizeOverlay.className = 'builder-direct-resize';
    resizeOverlay.setAttribute('aria-hidden','true');
    resizeOverlay.innerHTML = '<span class="builder-direct-resize-label"></span><i class="tl" data-resize-handle="tl"></i><i class="t" data-resize-handle="t"></i><i class="tr" data-resize-handle="tr"></i><i class="r" data-resize-handle="r"></i><i class="br" data-resize-handle="br"></i><i class="b" data-resize-handle="b"></i><i class="bl" data-resize-handle="bl"></i><i class="l" data-resize-handle="l"></i>';
    document.body.appendChild(resizeOverlay);
    resizeOverlay.querySelectorAll('[data-resize-handle]').forEach(function(handle){
      handle.addEventListener('pointerdown',function(event){
        if (!resizeTarget || !resizeBlock) return;
        event.preventDefault();
        event.stopPropagation();
        var direction = String(handle.getAttribute('data-resize-handle') || '');
        var startRect = resizeTarget.getBoundingClientRect();
        var startX = event.clientX;
        var startY = event.clientY;
        var startW = startRect.width;
        var startH = startRect.height;
        var parent = resizeTarget.parentElement || document.body;
        var parentW = Math.max(24,parent.getBoundingClientRect().width);
        var right = direction.indexOf('r') !== -1;
        var left = direction.indexOf('l') !== -1;
        var top = direction.indexOf('t') !== -1;
        var bottom = direction.indexOf('b') !== -1;
        var horizontal = right || left;
        var vertical = top || bottom;
        var latest = {width:startW,height:startH};

        function move(e){
          var dx = e.clientX - startX;
          var dy = e.clientY - startY;
          if (horizontal) {
            latest.width = Math.max(24,Math.min(parentW,startW + (right ? dx : -dx)));
            resizeTarget.style.width = Math.round(latest.width) + 'px';
            resizeTarget.style.maxWidth = '100%';
          }
          if (vertical) {
            latest.height = Math.max(0,Math.min(4000,startH + (bottom ? dy : -dy)));
            resizeTarget.style.minHeight = Math.round(latest.height) + 'px';
          }
          scheduleResizeOverlay();
        }
        function up(){
          document.removeEventListener('pointermove',move,true);
          document.removeEventListener('pointerup',up,true);
          document.removeEventListener('pointercancel',up,true);
          var finalRect = resizeTarget.getBoundingClientRect();
          var finalParent = resizeTarget.parentElement || document.body;
          var finalParentW = Math.max(1,finalParent.getBoundingClientRect().width);
          sendToParent({
            type:'milad-builder-direct-resize',
            key:resizeBlock.dataset.builderKey || '',
            nodeId:resizeNodeId,
            widthPercent:Math.max(1,Math.min(100,(finalRect.width / finalParentW) * 100)),
            widthPx:Math.round(finalRect.width),
            height:Math.round(finalRect.height),
            handle:direction
          });
        }
        document.addEventListener('pointermove',move,true);
        document.addEventListener('pointerup',up,true);
        document.addEventListener('pointercancel',up,true);
      },true);
    });
    return resizeOverlay;
  }

  function scheduleResizeOverlay(){
    if (resizeFrame) return;
    resizeFrame = window.requestAnimationFrame(function(){
      resizeFrame = 0;
      positionResizeOverlay();
    });
  }
  function positionResizeOverlay(){
    if (!resizeTarget || !resizeOverlay || !resizeTarget.isConnected) {
      if (resizeOverlay) resizeOverlay.hidden = true;
      return;
    }
    var rect = resizeTarget.getBoundingClientRect();
    resizeOverlay.hidden = false;
    resizeOverlay.style.left = rect.left + 'px';
    resizeOverlay.style.top = rect.top + 'px';
    resizeOverlay.style.width = rect.width + 'px';
    resizeOverlay.style.height = rect.height + 'px';
    var label = resizeOverlay.querySelector('.builder-direct-resize-label');
    if (label) label.textContent = Math.round(rect.width) + ' × ' + Math.round(rect.height) + ' px';
  }
  function selectResizeTarget(target){
    var block = target && target.closest('[data-builder-key]');
    if (!block) return;
    resizeBlock = block;
    var widget = target.closest('[data-widget-node]');
    resizeNodeId = widget && block.contains(widget) ? String(widget.dataset.widgetNode || '') : '';
    resizeTarget = widget || block;
    ensureResizeOverlay();
    positionResizeOverlay();
    if (resizeObserver) resizeObserver.disconnect();
    if (typeof ResizeObserver === 'function') {
      resizeObserver = new ResizeObserver(scheduleResizeOverlay);
      resizeObserver.observe(resizeTarget);
      if (resizeTarget.parentElement) resizeObserver.observe(resizeTarget.parentElement);
    }
  }
  document.addEventListener('click',function(event){
    var target = event.target.closest('[data-widget-node],[data-builder-key]');
    if (target) selectResizeTarget(target);
  },false);
  window.addEventListener('scroll',scheduleResizeOverlay,true);
  window.addEventListener('resize',scheduleResizeOverlay);
  window.addEventListener('message',function(event){
    if (event.source !== window.parent) return;
    var message = event.data || {};
    if (message.type === 'milad-builder-ping') {
      sendToParent({type:'milad-builder-ready'});
      return;
    }
    if (message.type === 'milad-builder-state') applyState(message);
    if (message.type === 'milad-builder-select-outline') selectOutline(String(message.key || ''),String(message.nodeId || ''),String(message.legacyField || ''));
  });
  sendToParent({type:'milad-builder-ready'});
})();
</script>
HTML;
}

/**
 * Turn the authenticated homepage preview into a self-contained srcdoc document.
 * No iframe navigation, host name, absolute URL, or external asset request is needed.
 */
function builder_make_internal_preview(string $html): string
{
    $cssPath = MILAD_ROOT . '/assets/css/site.css';
    $jsPath = MILAD_ROOT . '/assets/js/site.js';
    $avatarPath = MILAD_ROOT . '/milad_avatar.jpg';
    $css = is_file($cssPath) ? file_get_contents($cssPath) : false;
    $js = is_file($jsPath) ? file_get_contents($jsPath) : false;
    $avatar = is_file($avatarPath) ? file_get_contents($avatarPath) : false;

    if ($css === false || $js === false || $avatar === false) {
        throw new RuntimeException('یکی از فایل‌های محلی پیش‌نمایش برای ساخت srcdoc در دسترس نیست.');
    }

    $styleTag = '<style id="builder-embedded-site-css">' . $css . '</style>';
    $html = str_replace(
        '<link rel="stylesheet" href="./assets/css/site.css">',
        $styleTag,
        $html
    );

    $inlineJs = str_ireplace('</script', '<\\/script', $js);
    // Module scripts are deferred until the srcdoc document has been parsed, like the original defer script.
    $scriptTag = '<script type="module" id="builder-embedded-site-js">' . $inlineJs . '</script>';
    $html = str_replace(
        '<script src="./assets/js/site.js" defer></script>',
        $scriptTag,
        $html
    );

    if (strpos($html, $styleTag) === false || strpos($html, $scriptTag) === false) {
        throw new RuntimeException('فایل‌های استایل یا اسکریپت در قالب پیش‌نمایش پیدا نشدند.');
    }

    $imageUri = 'data:image/jpeg;base64,' . base64_encode($avatar);
    $html = str_replace(['./milad_avatar.jpg', 'milad_avatar.jpg'], $imageUri, $html);
    $html = preg_replace_callback(
        '/<img\\b(?=[^>]*\\balt="تصویر پروفایل میلاد")[^>]*>/i',
        static function (array $match) use ($imageUri): string {
            return preg_replace('/\\bsrc="[^"]*"/i', 'src="' . $imageUri . '"', $match[0], 1) ?? $match[0];
        },
        $html
    ) ?? $html;

    // Metadata and schema links are irrelevant inside an editor preview.
    $html = preg_replace_callback(
        '/<script\\b[^>]*>.*?<\\/script>/is',
        static function (array $match): string {
            return stripos($match[0], 'application/ld+json') !== false ? '' : $match[0];
        },
        $html
    ) ?? $html;
    $html = preg_replace_callback(
        '/<link\\b[^>]*>/i',
        static function (array $match): string {
            $tag = strtolower($match[0]);
            foreach (['rel="canonical"', "rel='canonical'", 'rel="icon"', "rel='icon'", 'rel="preload"', "rel='preload'"] as $needle) {
                if (strpos($tag, $needle) !== false) return '';
            }
            return $match[0];
        },
        $html
    ) ?? $html;
    $html = preg_replace_callback(
        '/<meta\\b[^>]*>/i',
        static function (array $match): string {
            $tag = strtolower($match[0]);
            if (strpos($tag, 'property="og:') !== false || strpos($tag, "property='og:") !== false ||
                strpos($tag, 'name="twitter:') !== false || strpos($tag, "name='twitter:") !== false) {
                return '';
            }
            return $match[0];
        },
        $html
    ) ?? $html;

    // Fail closed if a future template change reintroduces remote assets.
    $remoteAssetPattern = "#<(?:script|link|img|source)\\\\b[^>]*(?:src|href|srcset)=[\\\"']https?://#i";
    if (preg_match($remoteAssetPattern, $html)) {
        throw new RuntimeException('قالب پیش‌نمایش هنوز وابستگی مستقیم به منبع خارجی دارد.');
    }
    if (strpos($html, './assets/') !== false || strpos($html, '../assets/') !== false) {
        throw new RuntimeException('قالب پیش‌نمایش هنوز به مسیر فایل خارجی وابسته است.');
    }
    return $html;
}

