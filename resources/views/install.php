<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>نصب Milad CMS</title><link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="auth-body">
<main class="auth-wrap installer-wrap">
  <div class="auth-brand"><span class="brand-mark">M<span>.</span></span><div><strong>Milad CMS</strong><small>نصب و راه‌اندازی</small></div></div>
  <div class="auth-card">
    <div class="eyebrow">WELCOME / INSTALLATION WIZARD</div>
    <h1>راه‌اندازی سایت</h1>
    <p class="muted">همه مراحل نصب از همین صفحه انجام می‌شود؛ به SSH یا اجرای دستور نیاز نداری.</p>
    <?php if(!empty($error)): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <div class="installer-steps">
      <div class="installer-step <?= $activationReady?'done':'active' ?>"><span>۱</span><div><strong>فعال‌سازی نصب</strong><small>یک فایل کوچک در File Manager هاست بساز</small></div></div>
      <div class="installer-step <?= $activationReady?'active':'' ?>"><span>۲</span><div><strong>تنظیم دیتابیس و سایت</strong><small>اطلاعات اتصال و دامنه را وارد کن</small></div></div>
      <div class="installer-step"><span>۳</span><div><strong>ساخت حساب مدیر</strong><small>حساب مالک سایت ساخته می‌شود</small></div></div>
      <div class="installer-step"><span>۴</span><div><strong>نصب و ورود</strong><small>جداول ایجاد می‌شوند و نصب قفل می‌شود</small></div></div>
    </div>
    <h2 class="form-section-title">بررسی پیش‌نیازهای سرور</h2>
    <div class="requirements"><?php foreach($requirements as $r): ?><div class="requirement"><span class="<?= $r['ok']?'ok':'bad' ?>"><?= $r['ok']?'✓':'!' ?></span><?= e($r['label']) ?></div><?php endforeach; ?></div>

    <?php if(!$activationReady): ?>
      <section class="activation-guide">
        <div class="activation-guide-icon">1</div><div>
          <h2>فقط یک فایل از File Manager بساز</h2>
          <p>وارد کنترل‌پنل هاست شو، File Manager را باز کن و در پوشه <code>storage</code> پروژه یک فایل جدید با نام زیر بساز:</p>
          <div class="copy-field"><code>install.enabled</code><button type="button" data-copy="install.enabled">کپی نام</button></div>
          <p>محتوای فایل باید دقیقاً این عبارت باشد:</p>
          <div class="copy-field"><code>MILAD-CMS-ALLOW-FIRST-INSTALL</code><button type="button" data-copy="MILAD-CMS-ALLOW-FIRST-INSTALL">کپی متن</button></div>
          <p class="activation-note">مسیر نهایی فایل: <code>storage/install.enabled</code> — این فایل را داخل <code>public</code> نساز. پس از ساخت، همین صفحه را تازه‌سازی کن.</p>
        </div>
      </section>
    <?php else: ?>
      <div class="notice success">فعال‌سازی نصب تأیید شد. پیش‌نیازها را بررسی کن و فرم نصب را تکمیل کن.</div>
      <form method="post" class="stack-form">
        <?= csrf_field() ?>
        <h2 class="form-section-title">۱. اطلاعات پایگاه داده MySQL</h2>
        <div class="form-grid">
          <label>میزبان دیتابیس<input name="db_host" value="<?= e($_POST['db_host']??'localhost') ?>" required></label>
          <label>پورت<input name="db_port" type="number" min="1" max="65535" value="<?= e($_POST['db_port']??'3306') ?>" required></label>
          <label>نام دیتابیس<input name="db_name" value="<?= e($_POST['db_name']??'') ?>" required></label>
          <label>نام کاربری دیتابیس<input name="db_user" value="<?= e($_POST['db_user']??'') ?>" required></label>
          <label class="full">رمز دیتابیس<input name="db_password" type="password" autocomplete="new-password"></label>
        </div>
        <h2 class="form-section-title">۲. اطلاعات وب‌سایت</h2>
        <div class="form-grid">
          <label>عنوان سایت<input name="site_title" value="<?= e($_POST['site_title']??'میلاد | فضای دیجیتال شخصی') ?>" required></label>
          <label>آدرس کامل سایت<input name="site_url" type="url" placeholder="https://example.com" value="<?= e($_POST['site_url']??'') ?>" required></label>
          <label class="full">منطقه زمانی<select name="timezone"><?php foreach(['Europe/Berlin','Asia/Tehran','UTC','Europe/London','America/New_York'] as $z): ?><option value="<?= e($z) ?>" <?= ($_POST['timezone']??'Europe/Berlin')===$z?'selected':'' ?>><?= e($z) ?></option><?php endforeach; ?></select></label>
        </div>
        <h2 class="form-section-title">۳. ساخت حساب مالک</h2>
        <div class="form-grid">
          <label>نام مدیر<input name="admin_name" value="<?= e($_POST['admin_name']??'') ?>" required></label>
          <label>ایمیل مدیر<input name="admin_email" type="email" value="<?= e($_POST['admin_email']??'') ?>" required></label>
          <label>رمز عبور (حداقل ۱۲ کاراکتر)<input name="admin_password" type="password" minlength="12" autocomplete="new-password" required></label>
          <label>تکرار رمز عبور<input name="admin_password_confirm" type="password" minlength="12" autocomplete="new-password" required></label>
        </div>
        <button class="button primary wide installer-submit" type="submit">تست اتصال و نصب Milad CMS <span>←</span></button>
      </form>
    <?php endif; ?>
    <p class="tiny muted">اطلاعات دیتابیس در <code>config/local.php</code> خارج از مسیر عمومی ذخیره می‌شود. پس از نصب موفق، فایل فعال‌سازی حذف و نصب‌کننده برای همیشه قفل خواهد شد.</p>
  </div>
</main>
</body>
</html>
