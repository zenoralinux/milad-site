<?php
declare(strict_types=1);
function handle_login(): void {
    if(current_user())redirect('admin');$error=null;
    if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
        $q=db()->prepare('SELECT id,password_hash,is_active FROM users WHERE email=:email LIMIT 1');$q->execute(['email'=>$email]);$u=$q->fetch();
        if($u&&(int)$u['is_active']===1&&password_verify($password,$u['password_hash'])){
            session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=:id')->execute(['id'=>$u['id']]);
            log_activity('auth.login','user',(int)$u['id']);redirect('admin');
        }usleep(250000);$error='ایمیل یا رمز عبور نادرست است.';
    }
    render_view('login',['error'=>$error],'none');
}
function handle_logout(): void {
    verify_csrf();$u=current_user();log_activity('auth.logout','user',(int)($u['id']??0));$_SESSION=[];
    if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);}
    session_destroy();redirect('login');
}
function handle_dashboard(): void {
    require_permission('dashboard.view');$p=db();
    $stats=['pages'=>(int)$p->query('SELECT COUNT(*) FROM pages')->fetchColumn(),'published'=>(int)$p->query("SELECT COUNT(*) FROM pages WHERE status='published'")->fetchColumn(),'projects'=>(int)$p->query('SELECT COUNT(*) FROM projects')->fetchColumn(),'media'=>(int)$p->query('SELECT COUNT(*) FROM media')->fetchColumn()];
    $activity=$p->query('SELECT a.*,u.name AS user_name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 8')->fetchAll();
    render_view('admin/dashboard',['stats'=>$stats,'activity'=>$activity,'pageTitle'=>'داشبورد']);
}
function handle_pages(): void {
    require_permission('pages.manage');$pdo=db();
    if(preg_match('~^/admin/pages/(\d+)/delete$~',request_path(),$m)&&$_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$id=(int)$m[1];$pdo->prepare('DELETE FROM pages WHERE id=:id')->execute(['id'=>$id]);log_activity('page.delete','page',$id);flash('success','صفحه حذف شد.');redirect('admin/pages');}
    if(preg_match('~^/admin/pages/(\d+)/edit$~',request_path(),$m)){$id=(int)$m[1];$q=$pdo->prepare('SELECT * FROM pages WHERE id=:id');$q->execute(['id'=>$id]);$page=$q->fetch();if(!$page){http_response_code(404);exit('صفحه پیدا نشد.');}if($_SERVER['REQUEST_METHOD']==='POST')save_page($id,$page);render_view('admin/page-form',['page'=>$page,'pageTitle'=>'ویرایش صفحه']);return;}
    if(request_path()==='/admin/pages/create'){if($_SERVER['REQUEST_METHOD']==='POST')save_page(null,null);render_view('admin/page-form',['page'=>null,'pageTitle'=>'ساخت صفحه']);return;}
    $pages=$pdo->query('SELECT id,title,slug,status,updated_at FROM pages ORDER BY updated_at DESC')->fetchAll();render_view('admin/pages',['pages'=>$pages,'pageTitle'=>'مدیریت صفحات']);
}
function save_page(?int $id,?array $old): never {
    verify_csrf();$title=trim((string)($_POST['title']??''));$slug=slugify((string)($_POST['slug']??$title));$excerpt=trim((string)($_POST['excerpt']??''));$body=HtmlSanitizer::clean((string)($_POST['body']??''));$status=in_array($_POST['status']??'draft',['draft','published'],true)?(string)$_POST['status']:'draft';
    if($title===''||$slug===''){flash('error','عنوان و نشانی صفحه الزامی است.');redirect($id?"admin/pages/{$id}/edit":'admin/pages/create');}
    $check=db()->prepare('SELECT id FROM pages WHERE slug=:slug AND id!=:id LIMIT 1');$check->execute(['slug'=>$slug,'id'=>$id??0]);
    if($check->fetch()){flash('error','این نشانی قبلاً استفاده شده است.');redirect($id?"admin/pages/{$id}/edit":'admin/pages/create');}
    if($id&&$old){
        db()->prepare('INSERT INTO page_revisions(page_id,title,excerpt,body_html,status,created_by,created_at) VALUES(:id,:t,:e,:b,:s,:u,NOW())')->execute(['id'=>$id,'t'=>$old['title'],'e'=>$old['excerpt'],'b'=>$old['body_html'],'s'=>$old['status'],'u'=>current_user()['id']]);
        db()->prepare('UPDATE pages SET title=:t,slug=:g,excerpt=:e,body_html=:b,status=:s,published_at=IF(:s2="published",COALESCE(published_at,NOW()),NULL),updated_at=NOW() WHERE id=:id')->execute(['t'=>$title,'g'=>$slug,'e'=>$excerpt,'b'=>$body,'s'=>$status,'s2'=>$status,'id'=>$id]);
        log_activity('page.update','page',$id);flash('success','صفحه به‌روزرسانی شد.');redirect("admin/pages/{$id}/edit");
    }
    db()->prepare('INSERT INTO pages(title,slug,excerpt,body_html,status,published_at,created_at,updated_at) VALUES(:t,:g,:e,:b,:s,IF(:s2="published",NOW(),NULL),NOW(),NOW())')->execute(['t'=>$title,'g'=>$slug,'e'=>$excerpt,'b'=>$body,'s'=>$status,'s2'=>$status]);
    $new=(int)db()->lastInsertId();log_activity('page.create','page',$new);flash('success','صفحه ساخته شد.');redirect("admin/pages/{$new}/edit");
}
function handle_projects(): void {
    require_permission('projects.manage');$pdo=db();
    if(preg_match('~^/admin/projects/(\d+)/delete$~',request_path(),$m)&&$_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$id=(int)$m[1];$pdo->prepare('DELETE FROM projects WHERE id=:id')->execute(['id'=>$id]);log_activity('project.delete','project',$id);flash('success','پروژه حذف شد.');redirect('admin/projects');}
    if(preg_match('~^/admin/projects/(\d+)/edit$~',request_path(),$m)){$id=(int)$m[1];$q=$pdo->prepare('SELECT * FROM projects WHERE id=:id');$q->execute(['id'=>$id]);$project=$q->fetch();if(!$project){http_response_code(404);exit('پروژه پیدا نشد.');}if($_SERVER['REQUEST_METHOD']==='POST')save_project($id);render_view('admin/project-form',['project'=>$project,'pageTitle'=>'ویرایش پروژه']);return;}
    if(request_path()==='/admin/projects/create'){if($_SERVER['REQUEST_METHOD']==='POST')save_project(null);render_view('admin/project-form',['project'=>null,'pageTitle'=>'افزودن پروژه']);return;}
    $projects=$pdo->query('SELECT id,title,slug,status,featured,updated_at FROM projects ORDER BY sort_order,id DESC')->fetchAll();render_view('admin/projects',['projects'=>$projects,'pageTitle'=>'مدیریت پروژه‌ها']);
}
function save_project(?int $id): never {
    verify_csrf();$title=trim((string)($_POST['title']??''));$slug=slugify((string)($_POST['slug']??$title));$summary=trim((string)($_POST['summary']??''));$description=trim((string)($_POST['description']??''));$website=safe_url((string)($_POST['website_url']??''));$repo=safe_url((string)($_POST['repository_url']??''));$status=in_array($_POST['status']??'draft',['draft','published'],true)?(string)$_POST['status']:'draft';$featured=isset($_POST['featured'])?1:0;
    if($title===''||$slug===''){flash('error','عنوان و نشانی پروژه الزامی است.');redirect($id?"admin/projects/{$id}/edit":'admin/projects/create');}
    $q=db()->prepare('SELECT id FROM projects WHERE slug=:g AND id!=:id LIMIT 1');$q->execute(['g'=>$slug,'id'=>$id??0]);if($q->fetch()){flash('error','این نشانی قبلاً استفاده شده است.');redirect($id?"admin/projects/{$id}/edit":'admin/projects/create');}
    $params=['t'=>$title,'g'=>$slug,'s'=>$summary,'d'=>$description,'w'=>$website,'r'=>$repo,'status'=>$status,'f'=>$featured];
    if($id){$params['id']=$id;db()->prepare('UPDATE projects SET title=:t,slug=:g,summary=:s,description=:d,website_url=:w,repository_url=:r,status=:status,featured=:f,updated_at=NOW() WHERE id=:id')->execute($params);log_activity('project.update','project',$id);flash('success','پروژه ذخیره شد.');redirect("admin/projects/{$id}/edit");}
    db()->prepare('INSERT INTO projects(title,slug,summary,description,website_url,repository_url,status,featured,created_at,updated_at) VALUES(:t,:g,:s,:d,:w,:r,:status,:f,NOW(),NOW())')->execute($params);$new=(int)db()->lastInsertId();log_activity('project.create','project',$new);flash('success','پروژه ساخته شد.');redirect("admin/projects/{$new}/edit");
}
function handle_media(): void {
    require_permission('media.manage');
    if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();
        if(isset($_POST['delete_id'])){$id=(int)$_POST['delete_id'];$q=db()->prepare('SELECT stored_name FROM media WHERE id=:id');$q->execute(['id'=>$id]);$stored=$q->fetchColumn();if($stored){db()->prepare('DELETE FROM media WHERE id=:id')->execute(['id'=>$id]);@unlink(APP_ROOT.'/storage/uploads/'.basename((string)$stored));log_activity('media.delete','media',$id);flash('success','رسانه حذف شد.');}redirect('admin/media');}
        $u=$_FILES['file']??null;if(!$u||$u['error']!==UPLOAD_ERR_OK||!is_uploaded_file($u['tmp_name'])){flash('error','بارگذاری فایل انجام نشد.');redirect('admin/media');}
        if((int)$u['size']>8*1024*1024){flash('error','حداکثر حجم مجاز ۸ مگابایت است.');redirect('admin/media');}
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($u['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/avif'=>'avif'];
        if(!isset($allowed[$mime])||@getimagesize($u['tmp_name'])===false){flash('error','فقط تصویر معتبر JPG، PNG، WebP، GIF یا AVIF مجاز است.');redirect('admin/media');}
        $dir=APP_ROOT.'/storage/uploads';if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir)){flash('error','پوشه رسانه ساخته نشد.');redirect('admin/media');}
        $stored=bin2hex(random_bytes(20)).'.'.$allowed[$mime];if(!move_uploaded_file($u['tmp_name'],$dir.'/'.$stored)){flash('error','ذخیره تصویر ناموفق بود.');redirect('admin/media');}
        $size=getimagesize($dir.'/'.$stored);db()->prepare('INSERT INTO media(original_name,stored_name,mime_type,file_size,width,height,alt_text,created_at) VALUES(:o,:s,:m,:z,:w,:h,"",NOW())')->execute(['o'=>mb_substr(basename((string)$u['name']),0,250),'s'=>$stored,'m'=>$mime,'z'=>(int)$u['size'],'w'=>(int)($size[0]??0),'h'=>(int)($size[1]??0)]);
        $id=(int)db()->lastInsertId();log_activity('media.upload','media',$id);flash('success','تصویر بارگذاری شد.');redirect('admin/media');
    }
    $items=db()->query('SELECT * FROM media ORDER BY id DESC')->fetchAll();render_view('admin/media',['items'=>$items,'pageTitle'=>'مدیریت رسانه']);
}
function serve_media(int $id): never {
    $q=db()->prepare('SELECT stored_name,mime_type FROM media WHERE id=:id LIMIT 1');$q->execute(['id'=>$id]);$m=$q->fetch();if(!$m){http_response_code(404);exit;}
    $path=APP_ROOT.'/storage/uploads/'.basename((string)$m['stored_name']);if(!is_file($path)){http_response_code(404);exit;}
    header('Content-Type: '.$m['mime_type']);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');header('Cache-Control: public,max-age=2592000,immutable');readfile($path);exit;
}
function handle_settings(): void {
    require_permission('settings.manage');
    if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$keys=['site_title','site_tagline','hero_title','hero_intro','contact_email','github_url','telegram_url'];
        $q=db()->prepare('INSERT INTO settings(setting_key,setting_value,updated_at) VALUES(:k,:v,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');
        foreach($keys as $key){$v=trim((string)($_POST[$key]??''));if(str_ends_with($key,'_url'))$v=safe_url($v);if($key==='contact_email'&&$v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL)){flash('error','ایمیل تماس معتبر نیست.');redirect('admin/settings');}$q->execute(['k'=>$key,'v'=>$v]);}
        log_activity('settings.update');flash('success','تنظیمات ذخیره شد.');redirect('admin/settings');
    }
    $settings=[];foreach(db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll() as $r)$settings[$r['setting_key']]=$r['setting_value'];
    render_view('admin/settings',['settings'=>$settings,'pageTitle'=>'تنظیمات سایت']);
}
function settings_all(): array {$s=[];foreach(db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll() as $r)$s[$r['setting_key']]=$r['setting_value'];return $s;}
function public_home(): void {
    $settings=settings_all();$projects=db()->query("SELECT p.*,m.id AS image_id FROM projects p LEFT JOIN media m ON m.id=p.image_media_id WHERE p.status='published' ORDER BY p.featured DESC,p.sort_order,p.id DESC LIMIT 12")->fetchAll();
    $pages=db()->query("SELECT title,slug FROM pages WHERE status='published' ORDER BY id LIMIT 8")->fetchAll();
    $layout=json_decode((string)($settings['homepage_layout']??'[]'),true);
    if(!is_array($layout)||!$layout)$layout=[['id'=>'hero','enabled'=>true],['id'=>'intro','enabled'=>true],['id'=>'projects','enabled'=>true],['id'=>'about','enabled'=>true],['id'=>'contact','enabled'=>true]];
    render_view('themes/milad/home',['settings'=>$settings,'projects'=>$projects,'pages'=>$pages,'layout'=>$layout],'none');
}
function public_page(string $slug): void {
    $q=db()->prepare("SELECT * FROM pages WHERE slug=:s AND status='published' LIMIT 1");$q->execute(['s'=>$slug]);$page=$q->fetch();
    if(!$page){http_response_code(404);render_view('error',['title'=>'صفحه پیدا نشد','message'=>'محتوای درخواستی وجود ندارد.'],'none');return;}
    render_view('themes/milad/page',['page'=>$page],'none');
}
