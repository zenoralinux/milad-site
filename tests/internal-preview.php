<?php
declare(strict_types=1);

define('MILAD_ROOT', dirname(__DIR__));
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

foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
}

if (in_array(false, $checks, true)) {
    exit(1);
}

echo "Internal preview is self-contained." . PHP_EOL;
