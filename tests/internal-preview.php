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
$previewScript = builder_preview_script();
$unsafeStyle = builder_widget_style(['font_size' => '12px;position:fixed', 'background' => 'url(javascript:alert(1))']);

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
    'preview script sends exact node selection messages' =>
        strpos($previewScript, 'milad-builder-node-select') !== false &&
        strpos($previewScript, 'data-widget-node') !== false &&
        strpos($previewScript, 'selectOutline(key,nodeId,legacyField)') !== false,
    'unsafe element styles are rejected' => $unsafeStyle === '',
]);

foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
}
if (in_array(false, $checks, true)) {
    exit(1);
}
echo "Internal preview and element editing checks passed." . PHP_EOL;
