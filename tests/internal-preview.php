<?php
declare(strict_types=1);

define('MILAD_ROOT', dirname(__DIR__));
if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
require MILAD_ROOT . '/app/page_builder.php';

$fixture = <<<'HTML'
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <link rel="stylesheet" href="./assets/css/site.css">
  <script src="./assets/js/site.js" defer></script>
  <link rel="icon" href="./favicon.svg">
  <link rel="canonical" href="https://example.test/">
  <meta property="og:image" content="https://example.test/og.svg">
  <script type="application/ld+json">{"url":"https://example.test/"}</script>
</head>
<body><img src="https://cdn.example.test/custom-profile.jpg" alt="تصویر پروفایل میلاد"></body>
</html>
HTML;

$output = builder_make_internal_preview($fixture);

$checks = [
    'stylesheet is embedded' => strpos($output, 'id="builder-embedded-site-css"') !== false,
    'site JavaScript is embedded and deferred' => strpos($output, '<script type="module" id="builder-embedded-site-js">') !== false,
    'profile image is embedded as data' => strpos($output, 'data:image/jpeg;base64,') !== false &&
        strpos($output, 'src="https://') === false && strpos($output, "src='https://") === false,
    'external metadata is removed' => strpos($output, 'https://example.test/') === false,
    'local asset paths are removed' => strpos($output, './assets/') === false && strpos($output, '../assets/') === false,
    'favicon reference is removed' => strpos($output, './favicon.svg') === false,
];

$nestedHtml = builder_render_nested_widget([
    'id' => 'widget-root-test',
    'type' => 'container',
    'content' => ['direction' => 'column'],
    'children' => [
        [
            'id' => 'node-heading-test',
            'type' => 'heading',
            'content' => ['text' => 'Selectable heading', 'level' => 2],
            'styles' => ['text_color' => '#abcdef']
        ],
        [
            'id' => 'node-card-test',
            'type' => 'card',
            'content' => ['title' => 'Selectable card', 'body' => 'Card body'],
            'children' => [
                ['id' => 'node-child-copy-test', 'type' => 'paragraph', 'content' => ['text' => 'Nested copy']]
            ]
        ]
    ]
]);
$nestedResponsive = builder_responsive_css([[
    'block_key' => 'custom-test',
    'content' => [
        'children' => [[
            'id' => 'node-heading-test',
            'type' => 'heading',
            'styles' => ['responsive' => ['mobile' => ['font_size' => '20px']]]
        ]]
    ]
]]);
$legacyStyles = builder_legacy_element_css([[
    'block_key' => 'hero',
    'block_type' => 'hero',
    'content' => [
        'element_styles' => [
            'description' => [
                'font_size' => '21px',
                'text_color' => '#abcdef',
                'responsive' => ['mobile' => ['font_size' => '14px']]
            ]
        ]
    ]
]]);
$aboutHtml = builder_apply_to_html(
    '<!doctype html><html><head></head><body><main id="main"><section class="about-section section-shell" id="about"><div class="about-heading"><h2>About</h2><p>Intro</p></div><div class="principles-grid"><article class="principle-card">Old card</article></div></section></main></body></html>',
    [[
        'id' => 11,
        'block_key' => 'about',
        'block_type' => 'about',
        'label' => 'درباره من',
        'content' => ['principles_items' => [[
            'id' => 'principle-card-test',
            'type' => 'principle_item',
            'number' => '01',
            'title' => 'قابل انتخاب',
            'description' => 'کارت مستقل',
            'styles' => ['text_color' => '#abcdef']
        ]]],
        'settings' => [],
        'is_visible' => true
    ]],
    []
);
$statsHtml = builder_render_custom_block([
    'id' => 12,
    'block_key' => 'stats-test',
    'block_type' => 'stats',
    'label' => 'آمار',
    'content' => ['title' => 'آمار', 'items' => [[
        'id' => 'stat-card-test',
        'type' => 'stat_item',
        'value' => '12+',
        'label' => 'پروژه',
        'description' => 'توضیح',
        'styles' => ['radius' => '12px']
    ]]],
    'settings' => []
]);
$previewScript = builder_preview_script();
$unsafeStyle = builder_widget_style(['font_size' => '12px;position:fixed', 'background' => 'url(javascript:alert(1))']);
$multiValueStyle = builder_widget_style(['margin' => '0 auto 16px', 'padding' => '12px 18px']);

$checks = array_merge($checks, [
    'nested widgets expose stable selectable IDs' =>
        strpos($nestedHtml, 'data-widget-node="widget-root-test"') !== false &&
        strpos($nestedHtml, 'data-widget-node="node-heading-test"') !== false &&
        strpos($nestedHtml, 'data-widget-node="node-child-copy-test"') !== false,
    'nested widget responsive rules target exact node IDs' =>
        strpos($nestedResponsive, '[data-widget-node="node-heading-test"]{font-size:20px}') !== false &&
        strpos($nestedResponsive, '@media (max-width: 767px)') !== false,
    'legacy element styles persist with responsive overrides' =>
        strpos($legacyStyles, '[data-builder-key="hero"] .hero-description{color:#abcdef;font-size:21px}') !== false &&
        strpos($legacyStyles, '@media (max-width: 767px)') !== false &&
        strpos($legacyStyles, 'font-size:14px') !== false,
    'about principle cards are rendered with their own node IDs' =>
        strpos($aboutHtml, 'data-widget-node="principle-card-test"') !== false &&
        strpos($aboutHtml, 'قابل انتخاب') !== false,
    'stats cards render with independent element styling' =>
        strpos($statsHtml, 'data-widget-node="stat-card-test"') !== false &&
        strpos($statsHtml, 'border-radius:12px') !== false,
    'preview script supports opaque srcdoc origins safely' =>
        strpos($previewScript, "function sendToParent(message)") !== false &&
        strpos($previewScript, "window.parent.postMessage(message,'*')") !== false &&
        strpos($previewScript, 'event.source !== window.parent') !== false &&
        strpos($previewScript, ',location.origin);') === false,
    'preview script sends exact node selection messages' =>
        strpos($previewScript, 'milad-builder-node-select') !== false &&
        strpos($previewScript, 'data-widget-node') !== false &&
        strpos($previewScript, 'selectOutline(key,nodeId,legacyField)') !== false,
    'preview supports project-card selection and structured updates' =>
        strpos($previewScript, 'milad-builder-project-select') !== false &&
        strpos($previewScript, 'function applyProjectState(projects)') !== false,
    'preview exposes all eight resize handles and safe context messages' =>
        substr_count($previewScript, 'data-resize-handle=') === 8 &&
        strpos($previewScript, 'milad-builder-context-menu') !== false &&
        strpos($previewScript, 'event.source !== window.parent') !== false,
    'preview blocks edits on locked blocks and widgets' =>
        strpos($previewScript, 'data-builder-locked') !== false &&
        strpos($previewScript, 'function selectResizeTarget(target)') !== false,
    'unsafe element styles are rejected' => $unsafeStyle === '',
    'safe multi-value margin and padding are accepted' =>
        strpos($multiValueStyle, 'margin:0 auto 16px') !== false &&
        strpos($multiValueStyle, 'padding:12px 18px') !== false,
]);

foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
}
if (in_array(false, $checks, true)) {
    exit(1);
}
echo "Internal preview and element editing checks passed." . PHP_EOL;
