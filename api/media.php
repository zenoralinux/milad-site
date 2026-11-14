<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
start_admin_session();
$admin = require_admin();

function media_fail(string $code, string $message, int $status = 400): void
{
    json_response(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
}

function media_item(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['file_size'] = (int) $row['file_size'];
    $row['url'] = app_url((string) $row['storage_path']);
    return $row;
}

$pdo = db();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT id,filename,storage_path,mime_type,file_size,alt_text,created_at FROM media_library ORDER BY created_at DESC,id DESC LIMIT 200');
    $files = array_map('media_item', $stmt->fetchAll(PDO::FETCH_ASSOC));
    json_response(['ok' => true, 'data' => ['files' => $files]]);
}

if ($method !== 'POST') media_fail('METHOD_NOT_ALLOWED', 'متد درخواست مجاز نیست.', 405);
verify_csrf();

$action = (string) ($_POST['action'] ?? 'upload');
if ($action === 'upload') {
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) media_fail('FILE_REQUIRED', 'فایلی انتخاب نشده است.', 422);
    $file = $_FILES['file'];
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) media_fail('UPLOAD_FAILED', 'بارگذاری فایل کامل نشد.', 422);
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 30 * 1024 * 1024) media_fail('FILE_TOO_LARGE', 'حداکثر اندازه فایل ۳۰ مگابایت است.', 413);
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) media_fail('INVALID_UPLOAD', 'فایل ارسالی معتبر نیست.', 422);
    if (!class_exists('finfo')) media_fail('MIME_DETECTION_UNAVAILABLE', 'امکان بررسی نوع فایل روی سرور فعال نیست.', 503);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm'
    ];
    if (!isset($types[$mime])) media_fail('UNSUPPORTED_FILE_TYPE', 'فقط تصاویر JPG، PNG، WebP، GIF و ویدیوهای MP4 و WebM مجاز هستند.', 415);
    $extension = $types[$mime];
    $directory = MILAD_ROOT . '/uploads/media';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        media_fail('STORAGE_UNAVAILABLE', 'پوشه ذخیره رسانه ساخته نشد.', 500);
    }
    $filename = bin2hex(random_bytes(18)) . '.' . $extension;
    $absolute = $directory . '/' . $filename;
    if (!move_uploaded_file($tmp, $absolute)) media_fail('STORAGE_FAILED', 'ذخیره فایل روی سرور ناموفق بود.', 500);
    @chmod($absolute, 0644);
    $relative = 'uploads/media/' . $filename;
    $alt = trim((string) ($_POST['alt_text'] ?? ''));
    $insert = $pdo->prepare('INSERT INTO media_library (filename,storage_path,mime_type,file_size,alt_text) VALUES (?,?,?,?,?)');
    $insert->execute([$filename,$relative,$mime,$size,mb_substr($alt,0,500)]);
    $id = (int) $pdo->lastInsertId();
    log_activity((int) $admin['id'], 'upload', 'media', $id, 'بارگذاری فایل رسانه');
    $stmt = $pdo->prepare('SELECT id,filename,storage_path,mime_type,file_size,alt_text,created_at FROM media_library WHERE id=?');
    $stmt->execute([$id]);
    json_response(['ok' => true, 'data' => ['file' => media_item($stmt->fetch(PDO::FETCH_ASSOC))]], 201);
}

$body = json_body();
$id = (int) ($body['id'] ?? 0);
if ($id < 1) media_fail('INVALID_ID', 'شناسه فایل معتبر نیست.', 422);
$find = $pdo->prepare('SELECT id,filename,storage_path FROM media_library WHERE id=? LIMIT 1');
$find->execute([$id]);
$row = $find->fetch(PDO::FETCH_ASSOC);
if (!$row) media_fail('NOT_FOUND', 'فایل رسانه پیدا نشد.', 404);

if ($action === 'alt') {
    $alt = trim((string) ($body['alt_text'] ?? ''));
    $pdo->prepare('UPDATE media_library SET alt_text=? WHERE id=?')->execute([mb_substr($alt,0,500),$id]);
    json_response(['ok' => true, 'data' => ['id' => $id, 'alt_text' => mb_substr($alt,0,500)]]);
}
if ($action === 'delete') {
    $relative = (string) $row['storage_path'];
    if (strpos($relative, 'uploads/media/') !== 0 || strpos($relative, '..') !== false) media_fail('INVALID_STORAGE_PATH', 'مسیر فایل معتبر نیست.', 409);
    $absolute = MILAD_ROOT . '/' . $relative;
    if (is_file($absolute)) @unlink($absolute);
    $pdo->prepare('DELETE FROM media_library WHERE id=?')->execute([$id]);
    log_activity((int) $admin['id'], 'delete', 'media', $id, 'حذف فایل رسانه');
    json_response(['ok' => true, 'data' => ['id' => $id]]);
}
media_fail('INVALID_ACTION', 'عملیات رسانه معتبر نیست.', 422);
