<?php
declare(strict_types=1);
final class Installer
{
    public static function handle(): void {
        if(app_installed()){http_response_code(404);render_view('error',['title'=>'نصب انجام شده است','message'=>'نصب‌کننده پس از راه‌اندازی قفل شده است.'],'none');return;}
        $requirements=self::requirements();$error=null;
        if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{
            foreach($requirements as $r)if(!$r['ok'])throw new RuntimeException('ابتدا پیش‌نیازهای محیط را برطرف کنید.');
            self::install();redirect('login?installed=1');
        }catch(Throwable $ex){$error=$ex->getMessage();}}
        render_view('install',['requirements'=>$requirements,'error'=>$error],'none');
    }
    private static function requirements(): array {
        return [
            ['label'=>'PHP 8.1 یا جدیدتر','ok'=>version_compare(PHP_VERSION,'8.1.0','>=')],
            ['label'=>'افزونه PDO','ok'=>extension_loaded('pdo')],
            ['label'=>'درایور PDO MySQL','ok'=>extension_loaded('pdo_mysql')],
            ['label'=>'افزونه Fileinfo','ok'=>extension_loaded('fileinfo')],
            ['label'=>'افزونه DOM','ok'=>extension_loaded('dom')],
            ['label'=>'افزونه JSON','ok'=>extension_loaded('json')],
            ['label'=>'افزونه OpenSSL','ok'=>extension_loaded('openssl')],
            ['label'=>'افزونه mbstring','ok'=>extension_loaded('mbstring')],
            ['label'=>'دسترسی نوشتن config و storage','ok'=>is_writable(APP_ROOT.'/config')&&is_writable(APP_ROOT.'/storage')],
        ];
    }
    private static function install(): void {
        $name=trim((string)($_POST['admin_name']??''));$email=strtolower(trim((string)($_POST['admin_email']??'')));
        $pass=(string)($_POST['admin_password']??'');$confirm=(string)($_POST['admin_password_confirm']??'');
        $title=trim((string)($_POST['site_title']??''));$siteUrl=rtrim(trim((string)($_POST['site_url']??'')),'/');
        $tz=trim((string)($_POST['timezone']??'Europe/Berlin'));$host=trim((string)($_POST['db_host']??'127.0.0.1'));
        $port=(int)($_POST['db_port']??3306);$database=trim((string)($_POST['db_name']??''));$dbUser=trim((string)($_POST['db_user']??''));$dbPass=(string)($_POST['db_password']??'');
        if($name===''||mb_strlen($name)>120)throw new RuntimeException('نام مدیر معتبر نیست.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('ایمیل مدیر معتبر نیست.');
        if(strlen($pass)<12||!preg_match('/[A-Za-z]/',$pass)||!preg_match('/[0-9]/',$pass))throw new RuntimeException('رمز باید حداقل ۱۲ کاراکتر و شامل حروف و عدد باشد.');
        if(!hash_equals($pass,$confirm))throw new RuntimeException('تکرار رمز عبور مطابقت ندارد.');
        if($title===''||!filter_var($siteUrl,FILTER_VALIDATE_URL)||!in_array(parse_url($siteUrl,PHP_URL_SCHEME),['http','https'],true))throw new RuntimeException('عنوان یا URL سایت معتبر نیست.');
        if($host===''||$database===''||$dbUser===''||$port<1||$port>65535||!preg_match('/^[a-zA-Z0-9_$-]+$/',$database))throw new RuntimeException('اطلاعات دیتابیس معتبر یا کامل نیست.');
        if(!in_array($tz,DateTimeZone::listIdentifiers(),true))throw new RuntimeException('منطقه زمانی معتبر نیست.');
        $pdo=new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        $schema=file_get_contents(APP_ROOT.'/database/schema.sql');if($schema===false)throw new RuntimeException('فایل schema.sql پیدا نشد.');
        foreach(array_filter(array_map('trim',explode(';',$schema))) as $sql)$pdo->exec($sql);
        $pdo->beginTransaction();
        try{
            $pdo->exec("INSERT IGNORE INTO roles(slug,name) VALUES('owner','مالک'),('administrator','مدیر'),('editor','ویرایشگر')");
            $perms=['dashboard.view'=>'مشاهده داشبورد','pages.manage'=>'مدیریت صفحات','projects.manage'=>'مدیریت پروژه‌ها','media.manage'=>'مدیریت رسانه','settings.manage'=>'مدیریت تنظیمات','users.manage'=>'مدیریت کاربران'];
            $pi=$pdo->prepare('INSERT IGNORE INTO permissions(slug,description) VALUES(:s,:d)');foreach($perms as $s=>$d)$pi->execute(['s'=>$s,'d'=>$d]);
            $roleId=(int)$pdo->query("SELECT id FROM roles WHERE slug='owner'")->fetchColumn();
            $rp=$pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT :r,id FROM permissions');$rp->execute(['r'=>$roleId]);
            $ui=$pdo->prepare('INSERT INTO users(name,email,password_hash,is_active,created_at) VALUES(:n,:e,:p,1,NOW())');
            $ui->execute(['n'=>$name,'e'=>$email,'p'=>password_hash($pass,PASSWORD_DEFAULT)]);$userId=(int)$pdo->lastInsertId();
            $ur=$pdo->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:u,:r)');$ur->execute(['u'=>$userId,'r'=>$roleId]);
            $defaults=['site_title'=>$title,'site_tagline'=>'فضای دیجیتال شخصی','hero_title'=>'ایده‌ها را به تجربه تبدیل می‌کنم.','hero_intro'=>'من میلادم؛ به ساختن نرم‌افزار و حل مسئله علاقه دارم.','contact_email'=>$email,'github_url'=>'https://github.com/miladalizadw','telegram_url'=>'https://t.me/miladalizadw'];
            $si=$pdo->prepare('INSERT IGNORE INTO settings(setting_key,setting_value,updated_at) VALUES(:k,:v,NOW())');foreach($defaults as $k=>$v)$si->execute(['k'=>$k,'v'=>$v]);
            $pdo->commit();
        }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();throw $ex;}
        $config=['app'=>['name'=>$title,'base_url'=>$siteUrl,'timezone'=>$tz,'key'=>bin2hex(random_bytes(32))],
            'database'=>['host'=>$host,'port'=>$port,'name'=>$database,'user'=>$dbUser,'password'=>$dbPass]];
        $content="<?php\nreturn ".var_export($config,true).";\n";$tmp=APP_ROOT.'/config/local.php.tmp';
        if(file_put_contents($tmp,$content,LOCK_EX)===false||!rename($tmp,APP_ROOT.'/config/local.php')){@unlink($tmp);throw new RuntimeException('نوشتن فایل پیکربندی ناموفق بود.');}
        @chmod(APP_ROOT.'/config/local.php',0640);
        if(file_put_contents(APP_ROOT.'/storage/install.lock','installed '.gmdate('c'),LOCK_EX)===false)throw new RuntimeException('ساخت قفل نصب ناموفق بود.');
        @chmod(APP_ROOT.'/storage/install.lock',0640);
    }
}
