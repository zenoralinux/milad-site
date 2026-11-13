<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$root = dirname(__DIR__);
$storage = $root . '/storage';
if (!is_dir($storage) || !is_writable($storage)) {
    fwrite(STDERR, "The storage directory must exist and be writable.\n");
    exit(1);
}

$token = bin2hex(random_bytes(32));
$hash = password_hash($token, PASSWORD_DEFAULT);
$file = $storage . '/install-token.hash';
if (file_put_contents($file, $hash . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write the one-time install token.\n");
    exit(1);
}
@chmod($file, 0600);
fwrite(STDOUT, "One-time Milad CMS installer token (copy it now):\n{$token}\n");
fwrite(STDOUT, "The stored hash will be removed after a successful installation.\n");
