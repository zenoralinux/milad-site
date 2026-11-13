<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require APP_ROOT.'/app/Installer.php';
require APP_ROOT.'/app/Controllers.php';
$path=request_path();$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
// PHP's built-in server should serve existing assets directly.
if(PHP_SAPI==='cli-server'&&$path!=='/'&&is_file(__DIR__.$path))return false;
try{
    if($path==='/install'){Installer::handle();exit;}
    if(!app_installed())redirect('install');
    if($path==='/login'){handle_login();exit;}
    if($path==='/logout'&&$method==='POST'){handle_logout();exit;}
    if(preg_match('~^/media/(\d+)$~',$path,$m))serve_media((int)$m[1]);
    if($path==='/admin'){handle_dashboard();exit;}
    if($path==='/admin/pages'||$path==='/admin/pages/create'||preg_match('~^/admin/pages/\d+/(edit|delete)$~',$path)){handle_pages();exit;}
    if($path==='/admin/projects'||$path==='/admin/projects/create'||preg_match('~^/admin/projects/\d+/(edit|delete)$~',$path)){handle_projects();exit;}
    if($path==='/admin/media'){handle_media();exit;}
    if($path==='/admin/settings'){handle_settings();exit;}
    if($path==='/'){public_home();exit;}
    if(!str_starts_with($path,'/admin')&&!str_starts_with($path,'/api')){public_page(trim($path,'/'));exit;}
    http_response_code(404);render_view('error',['title'=>'صفحه پیدا نشد','message'=>'مسیر موردنظر وجود ندارد.'],'none');
}catch(Throwable $ex){
    error_log('[Milad CMS] '.$ex::class.': '.$ex->getMessage());http_response_code(500);
    $message=app_config('app.debug')?$ex->getMessage():'خطای داخلی رخ داد. گزارش خطا در error log سرور ثبت شده است.';
    render_view('error',['title'=>'خطای داخلی','message'=>$message],'none');
}
