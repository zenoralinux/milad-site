<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/HtmlSanitizer.php';

$input = '<p>Hello <strong>safe</strong><script>alert(1)</script><a href="javascript:alert(2)">unsafe</a><a href="https://example.com">external</a></p>';
$output = HtmlSanitizer::clean($input);

$checks = [
    'keeps paragraph and strong formatting' => str_contains($output, '<p>') && str_contains($output, '<strong>safe</strong>'),
    'removes script element and content' => !str_contains(strtolower($output), '<script') && !str_contains($output, 'alert(1)'),
    'removes javascript URLs' => !str_contains(strtolower($output), 'javascript:'),
    'adds safe external-link attributes' => str_contains($output, 'rel="nofollow noopener noreferrer"') && str_contains($output, 'target="_blank"'),
];

$failed = false;
foreach ($checks as $label => $passed) {
    echo ($passed ? '[OK]   ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
