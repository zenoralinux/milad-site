<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
start_admin_session();
$admin = null;
$setupError = null;
try { db(); $admin = current_admin(); } catch (Throwable $e) { $setupError = $e->getMessage(); }
$csrf = csrf_token();
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#080b12">
  <title>پنل مدیریت | میلاد</title><link rel="stylesheet" href="/assets/css/admin.css">
  <script src="/assets/js/admin.js" defer></script>
</head>
<body data-mode="<?= $setupError ? 'setup' : ($admin ? 'admin' : 'login') ?>" data-csrf="<?= h($csrf) ?>">
<?php if ($setupError): ?>
  <main class="setup-screen"><section class="setup-card">
    <div class="brand-mark">M<span>.</span></div><span class="eyebrow">ADMIN CMS · SETUP REQUIRED</span>
    <h1>پنل آماده راه‌اندازی است</h1><p>برای فعال شدن پنل، اتصال پایگاه داده را تنظیم و نصب اولیه را از خط فرمان اجرا کن.</p>
    <ol><li>فایل <code>config.example.php</code> را به <code>config.php</code> کپی کن.</li><li>اطلاعات MySQL را وارد و Secret را با یک مقدار تصادفی طولانی جایگزین کن.</li><li>در ترمینال اجرا کن: <code>php bin/install.php</code></li></ol>
    <p class="setup-error"><?= h(cfg('app.debug', false) ? $setupError : 'پیکربندی دیتابیس هنوز انجام نشده یا ارتباط با آن برقرار نیست.') ?></p>
    <a class="text-link" href="/">بازگشت به وب‌سایت ↗</a>
  </section></main>
<?php elseif (!$admin): ?>
  <main class="login-screen"><div class="login-decoration login-decoration-one"></div><div class="login-decoration login-decoration-two"></div>
    <section class="login-card">
      <a class="login-brand" href="/"><span class="brand-mark">M<span>.</span></span><span><strong>Milad</strong><small>Personal workspace</small></span></a>
      <span class="eyebrow">SECURE ADMIN ACCESS</span><h1>خوش برگشتی.</h1><p>برای مدیریت محتوای وب‌سایت وارد حساب مدیر شو.</p>
      <form id="loginForm" class="stack-form">
        <label>نام کاربری<input name="username" autocomplete="username" required minlength="3" autofocus></label>
        <label>رمز عبور<input name="password" type="password" autocomplete="current-password" required minlength="12"></label>
        <div class="form-error" id="loginError" role="alert"></div>
        <button class="primary-button" type="submit"><span>ورود به پنل</span><b>↙</b></button>
      </form><a class="back-link" href="/">← بازگشت به وب‌سایت</a>
    </section><span class="login-foot">MILAD DIGITAL SPACE · PRIVATE AREA</span>
  </main>
<?php else: ?>
  <div class="admin-app">
    <aside class="sidebar" id="sidebar">
      <a class="sidebar-brand" href="/"><span class="brand-mark">M<span>.</span></span><span><strong>Milad</strong><small>Control center</small></span></a>
      <div class="sidebar-label">WORKSPACE</div>
      <nav class="side-nav" aria-label="منوی مدیریت">
        <button class="side-link is-active" data-view="dashboard"><span>◫</span> نمای کلی</button>
        <button class="side-link" data-view="content"><span>▤</span> محتوای سایت</button>
        <button class="side-link" data-view="projects"><span>▦</span> مدیریت پروژه‌ها</button>
        <button class="side-link" data-view="settings"><span>⚙</span> تنظیمات سایت</button>
        <button class="side-link" data-view="activity"><span>◷</span> فعالیت‌های اخیر</button>
      </nav>
      <div class="sidebar-bottom">
        <div class="sidebar-user"><span class="user-avatar"><?= h(mb_substr((string) $admin['display_name'], 0, 1)) ?></span><span><strong><?= h($admin['display_name']) ?></strong><small>مدیر اصلی</small></span><i></i></div>
        <button class="side-link logout-link" id="logoutButton"><span>↗</span> خروج امن</button>
      </div>
    </aside>
    <main class="admin-main">
      <header class="admin-topbar"><div class="topbar-start"><button class="mobile-menu" id="mobileMenu" aria-label="باز کردن منو">☰</button><div><span class="eyebrow">PERSONAL WEBSITE / CMS</span><h1 id="pageTitle">نمای کلی</h1></div></div>
        <div class="topbar-actions"><span class="live-indicator"><i></i> سیستم مدیریت فعال</span><a class="view-site" href="/" target="_blank" rel="noopener">مشاهده سایت ↗</a></div>
      </header>
      <div class="admin-content">
        <section class="admin-view is-active" id="view-dashboard">
          <div class="welcome-row"><div><span class="section-kicker">OVERVIEW</span><h2>سلام <?= h($admin['display_name']) ?>،<br><span>اینجا مرکز کنترل سایت توست.</span></h2><p>وضعیت محتوای وب‌سایت و آخرین فعالیت‌ها را از اینجا مدیریت کن.</p></div><div class="welcome-orbit"><b>M</b><i></i></div></div>
          <div class="stat-grid" id="dashboardStats"><div class="stat-card skeleton"></div><div class="stat-card skeleton"></div><div class="stat-card skeleton"></div></div>
          <div class="dashboard-grid">
            <section class="panel-card"><div class="panel-heading"><div><span class="section-kicker">QUICK ACCESS</span><h3>دسترسی سریع</h3></div></div><div class="quick-actions">
              <button data-go="content"><b>▤</b><span><strong>ویرایش محتوای سایت</strong><small>متن‌ها و بخش‌های صفحه اصلی</small></span><i>↙</i></button>
              <button data-go="projects"><b>▦</b><span><strong>مدیریت پروژه‌ها</strong><small>ایجاد و ویرایش پروژه‌ها</small></span><i>↙</i></button>
              <button data-go="settings"><b>⚙</b><span><strong>تنظیمات عمومی</strong><small>اطلاعات پایه و لینک‌های سایت</small></span><i>↙</i></button>
            </div></section>
            <section class="panel-card"><div class="panel-heading"><div><span class="section-kicker">RECENT ACTIVITY</span><h3>آخرین تغییرات</h3></div><button class="inline-action" data-go="activity">مشاهده همه ↗</button></div><div class="activity-list" id="dashboardActivity"><p class="muted">در حال دریافت اطلاعات…</p></div></section>
          </div>
        </section>
        <section class="admin-view" id="view-content">
          <div class="view-intro"><div><span class="section-kicker">CONTENT STUDIO</span><h2>محتوای سایت</h2><p>متن‌ها و بخش‌های صفحه اصلی را بدون تغییر کد مدیریت کن.</p></div></div>
          <div class="content-layout"><div class="section-picker panel-card" id="sectionPicker"><div class="loading-line">در حال دریافت بخش‌ها…</div></div><section class="panel-card editor-panel" id="sectionEditor"><div class="empty-state"><b>✳</b><h3>یک بخش را انتخاب کن</h3><p>برای ویرایش، یکی از بخش‌های سمت راست را انتخاب کن.</p></div></section></div>
        </section>
        <section class="admin-view" id="view-projects">
          <div class="view-intro"><div><span class="section-kicker">PORTFOLIO MANAGER</span><h2>پروژه‌ها</h2><p>پروژه‌های نمایش‌داده‌شده در صفحه اصلی را کنترل کن.</p></div><button class="primary-button compact" id="newProjectButton">＋ پروژه جدید</button></div>
          <section class="panel-card"><div class="table-toolbar"><div class="search-field"><span>⌕</span><input type="search" id="projectSearch" placeholder="جست‌وجوی پروژه…"></div><select id="projectFilter"><option value="all">همه پروژه‌ها</option><option value="published">منتشرشده</option><option value="draft">پیش‌نویس</option><option value="os">سیستم‌عامل</option><option value="apps">اپلیکیشن</option><option value="tools">ابزارها</option></select><span class="result-count" id="projectCount">—</span></div><div class="project-admin-list" id="projectList"><div class="loading-line">در حال دریافت پروژه‌ها…</div></div></section>
        </section>
        <section class="admin-view" id="view-settings">
          <div class="view-intro"><div><span class="section-kicker">SITE CONFIGURATION</span><h2>تنظیمات سایت</h2><p>اطلاعات هویتی، لینک‌ها و داده‌های موتور جست‌وجو.</p></div></div>
          <form class="panel-card settings-form" id="settingsForm"><div class="form-grid" id="settingsFields"></div><div class="form-actions"><span class="form-hint">تنظیمات فقط پس از ذخیره اعمال می‌شوند.</span><button class="primary-button compact" type="submit">ذخیره تنظیمات <b>↙</b></button></div></form>
        </section>
        <section class="admin-view" id="view-activity"><div class="view-intro"><div><span class="section-kicker">AUDIT LOG</span><h2>فعالیت‌های اخیر</h2><p>تاریخچه ورود و تغییرات انجام‌شده در پنل.</p></div></div><section class="panel-card"><div class="activity-list full-activity" id="fullActivity"><div class="loading-line">در حال دریافت…</div></div></section></section>
      </div>
      <footer class="admin-footer"><span>Milad CMS · ساخته‌شده برای مدیریت فضای دیجیتال شخصی</span><span>SECURE WORKSPACE</span></footer>
    </main>
  </div>
  <div class="modal-backdrop" id="projectModal" hidden><section class="editor-modal" role="dialog" aria-modal="true" aria-labelledby="projectModalTitle">
    <div class="modal-heading"><div><span class="section-kicker">PROJECT EDITOR</span><h2 id="projectModalTitle">پروژه جدید</h2></div><button class="icon-close" type="button" data-close-modal>×</button></div>
    <form id="projectForm"><input type="hidden" name="id"><div class="form-grid">
      <label class="field-span-2">عنوان پروژه<input name="title" required maxlength="180"></label>
      <label>Slug<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="160" dir="ltr"></label>
      <label>دسته‌بندی<select name="category"><option value="os">سیستم‌عامل</option><option value="apps">اپلیکیشن</option><option value="tools">ابزارها</option></select></label>
      <label>برچسب نوع<input name="kind_label" maxlength="100" placeholder="اپلیکیشن · Web"></label>
      <label>وضعیت<select name="status"><option value="active">فعال</option><option value="in_progress">در حال توسعه</option><option value="paused">متوقف‌شده</option><option value="project">پروژه</option><option value="archived">بایگانی‌شده</option></select></label>
      <label class="field-span-2">توضیح کوتاه<textarea name="excerpt" rows="2" maxlength="500"></textarea></label>
      <label class="field-span-2">توضیحات کامل<textarea name="description" rows="4" maxlength="20000"></textarea></label>
      <label>قالب گرافیکی<select name="visual_variant"><option value="generic">عمومی</option><option value="zenora">Zenora</option><option value="chat">پیام‌رسان</option><option value="android">اندروید</option><option value="arm">ترمینال</option><option value="wsl">WSL</option></select></label>
      <label>ترتیب نمایش<input name="sort_order" type="number" min="0" max="100000" value="10"></label>
      <label class="field-span-2">فناوری‌ها <small>با ویرگول یا خط جدید جدا کن.</small><textarea name="technologies" rows="2" placeholder="PHP, JavaScript, Linux"></textarea></label>
      <label class="field-span-2">لینک‌ها <small>JSON با label و url؛ نمونه: [{"label":"GitHub","url":"https://github.com/..."}]</small><textarea name="links" rows="4" dir="ltr" spellcheck="false">[]</textarea></label>
      <label class="check-field"><input name="is_featured" type="checkbox"> پروژه ویژه</label>
      <label class="check-field"><input name="is_published" type="checkbox" checked> انتشار در سایت</label>
    </div><div class="form-actions"><span class="form-hint" id="projectFormHint"></span><button class="secondary-button" type="button" data-close-modal>انصراف</button><button class="primary-button compact" type="submit">ذخیره پروژه <b>↙</b></button></div></form>
  </section></div>
  <div class="toast-region" id="toastRegion" aria-live="polite"></div>
<?php endif; ?>
</body></html>
