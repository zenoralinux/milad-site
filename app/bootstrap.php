<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Database.php';
require_once APP_ROOT . '/app/HtmlSanitizer.php';
$local = APP_ROOT . '/config/local.php';
$GLOBALS['app_config'] = is_file($local) ? require $local : ['app'=>['name'=>'Milad CMS','base_url'=>'','timezone'=>'UTC']];
$tz = (string)($GLOBALS['app_config']['app']['timezone'] ?? 'UTC');
date_default_timezone_set(in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : 'UTC');

function app_config(?string $key=null): mixed {
    $value=$GLOBALS['app_config']??[]; if($key===null)return $value;
    foreach(explode('.',$key) as $part){if(!is_array($value)||!array_key_exists($part,$value))return null;$value=$value[$part];}
    return $value;
}

/**
 * Return the configured install path. Before installation, infer it from
 * SCRIPT_NAME so the wizard also works under /site using the root .htaccess.
 */
function base_path(): string {
    $configured = (string)(parse_url((string)(app_config('app.base_url')??''), PHP_URL_PATH) ?? '');
    if ($configured !== '') return rtrim($configured, '/');

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($directory === '.' || $directory === '/' || $directory === '/public') return '';
    if (str_ends_with($directory, '/public')) $directory = substr($directory, 0, -7);
    return rtrim($directory, '/');
}

function app_installed(): bool { return is_file(APP_ROOT.'/config/local.php'); }
function db(): PDO { return Database::connection(); }
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function url(string $path=''): string {
    $base = rtrim((string)(app_config('app.base_url')??''), '/');
    if ($base === '') $base = base_path();
    return $path === '' ? ($base === '' ? '/' : $base.'/') : $base.'/'.ltrim($path,'/');
}
function suggested_site_url(): string {
    $configured = rtrim((string)(app_config('app.base_url')??''), '/');
    if ($configured !== '') return $configured;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    return ($https ? 'https' : 'http').'://'.$host.base_path();
}
function redirect(string $path): never { header('Location: '.url($path),true,303);exit; }
function csrf_token(): string { if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(32));return (string)$_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { $v=(string)($_POST['_csrf']??'');if($v===''||!hash_equals(csrf_token(),$v)){http_response_code(419);exit('درخواست منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.');} }
function flash(string $type,string $message): void { $_SESSION['_flash'][]=['type'=>$type,'message'=>$message]; }
function pull_flash(): array { $v=$_SESSION['_flash']??[];unset($_SESSION['_flash']);return is_array($v)?$v:[]; }
function current_user(): ?array {
    static $done=false,$user=null;if($done)return $user;$done=true;$id=(int)($_SESSION['user_id']??0);
    if($id<1||!app_installed())return null;
    $q=db()->prepare('SELECT id,name,email,is_active FROM users WHERE id=:id LIMIT 1');$q->execute(['id'=>$id]);$u=$q->fetch();
    if(!$u||(int)$u['is_active']!==1){unset($_SESSION['user_id']);return null;}return $user=$u;
}
function require_login(): array { $u=current_user();if(!$u)redirect('login');return $u; }
function can(string $permission): bool {
    $u=current_user();if(!$u)return false;
    $q=db()->prepare('SELECT 1 FROM user_roles ur JOIN role_permissions rp ON rp.role_id=ur.role_id JOIN permissions p ON p.id=rp.permission_id WHERE ur.user_id=:uid AND p.slug=:slug LIMIT 1');
    $q->execute(['uid'=>$u['id'],'slug'=>$permission]);return (bool)$q->fetchColumn();
}
function require_permission(string $permission): array { $u=require_login();if(!can($permission)){http_response_code(403);exit('شما اجازه انجام این عملیات را ندارید.');}return $u; }
function log_activity(string $action,string $entity='system',?int $entityId=null,array $metadata=[]): void {
    try{$u=current_user();$q=db()->prepare('INSERT INTO activity_logs(user_id,action,entity_type,entity_id,metadata_json,ip_address,created_at) VALUES(:uid,:a,:t,:id,:m,:ip,NOW())');
    $q->execute(['uid'=>$u['id']??null,'a'=>$action,'t'=>$entity,'id'=>$entityId,'m'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);}catch(Throwable $ignored){}
}
function render_view(string $name,array $data=[],string $layout='admin'): void {
    $file=APP_ROOT.'/resources/views/'.$name.'.php';
    if(!is_file($file))$file=APP_ROOT.'/resources/'.$name.'.php';
    if(!is_file($file))throw new RuntimeException('View not found: '.$name);
    extract($data,EXTR_SKIP);ob_start();require $file;$content=(string)ob_get_clean();
    if($layout==='none'){echo $content;return;}require APP_ROOT.'/resources/views/layout.php';
}
function request_path(): string {
    $p=rawurldecode((string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??'/'));$b=base_path();
    if($b!==''&&($p===$b||str_starts_with($p,$b.'/')))$p=substr($p,strlen($b));
    $p='/'.trim($p,'/');return $p==='/'?'/':rtrim($p,'/');
}
function slugify(string $s): string { $s=mb_strtolower(trim($s));$s=preg_replace('/[^\pL\pN]+/u','-',$s)??'';return trim($s,'-'); }
function safe_url(string $s): string { $s=trim($s);if($s===''||!filter_var($s,FILTER_VALIDATE_URL))return '';return in_array(parse_url($s,PHP_URL_SCHEME),['http','https'],true)?$s:''; }

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('milad_cms_session');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['lifetime'=>0,'path'=>base_path() ?: '/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
