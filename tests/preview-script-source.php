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

$document = builder_preview_script();
$start = strpos($document, '<script>');
$end = strrpos($document, '</script>');
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "Could not locate the embedded builder preview script.\n");
    exit(1);
}
$start += strlen('<script>');
echo substr($document, $start, $end - $start);
