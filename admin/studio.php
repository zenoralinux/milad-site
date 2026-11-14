<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/page_builder.php';
start_admin_session();
$csrf = csrf_token();
$admin = current_admin();
if (!$admin) { header('Location: ' . app_url('admin')); exit; }
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$builderPreviewDocument = '';
$builderPreviewError = '';
$hadPreviewFlag = array_key_exists('builder_preview', $_GET);
$originalPreviewFlag = $_GET['builder_preview'] ?? null;
$_GET['builder_preview'] = '1';
$previewBufferLevel = ob_get_level();
ob_start();
try {
    include dirname(__DIR__) . '/index.php';
    $builderPreviewDocument = (string) ob_get_clean();
    $builderPreviewDocument = builder_make_internal_preview($builderPreviewDocument);
} catch (Throwable $previewError) {
    while (ob_get_level() > $previewBufferLevel) ob_end_clean();
    $builderPreviewError = 'ساخت پیش‌نمایش داخلی ناموفق بود.';
    error_log('[Milad Studio internal preview] ' . $previewError->getMessage());
}
if ($hadPreviewFlag) $_GET['builder_preview'] = $originalPreviewFlag; else unset($_GET['builder_preview']);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#080b12">
<title>Milad Studio Pro | ویرایشگر بصری</title>
<link rel="stylesheet" href="<?= h(app_url('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= h(app_url('assets/css/builder.css?v=studio-pro-20261114-4')) ?>">
<script src="<?= h(app_url('assets/js/builder.js?v=studio-pro-20261114-4')) ?>" defer></script>
</head>
<body class="studio-page" data-mode="admin" data-csrf="<?= h($csrf) ?>" data-base-path="<?= h(app_base_path()) ?>">
<header class="studio-topbar">
<a class="studio-brand" href="<?= h(app_url('admin')) ?>"><span class="brand-mark">M<span>.</span></span><span><strong>Milad Studio</strong><small>PRO VISUAL EDITOR</small></span></a>
<div class="studio-document"><span class="studio-live-dot"></span><span>صفحه اصلی</span><small>ذخیره خودکار فعال است</small></div>
<div class="studio-top-actions"><a class="secondary-button compact" href="<?= h(app_url('admin')) ?>">بازگشت به مدیریت</a><a class="secondary-button compact" href="<?= h(app_url('/')) ?>" target="_blank" rel="noopener">مشاهده سایت ↗</a></div>
</header>
<main class="studio-editor" id="studioEditor">
<div class="builder-toolbar panel-card">
            <div class="builder-toolbar-start"><strong>صفحه اصلی</strong><span class="builder-status" id="builderStatus">در حال آماده‌سازی…</span></div>
            <div class="builder-toolbar-end">
              <div class="builder-device-tabs" aria-label="اندازه پیش‌نمایش">
                <button type="button" class="is-active" data-device="desktop">دسکتاپ</button>
                <button type="button" data-device="tablet">تبلت</button>
                <button type="button" data-device="mobile">موبایل</button>
              </div>
              <button class="secondary-button" id="builderUndo" type="button" title="بازگشت (Ctrl+Z)" disabled>↶ بازگشت</button>
              <button class="secondary-button" id="builderRedo" type="button" title="انجام دوباره (Ctrl+Shift+Z)" disabled>↷ دوباره</button>
              <button class="secondary-button" id="builderTemplates" type="button">قالب‌های آماده</button>
              <button class="primary-button compact" id="builderSaveAll" type="button">ذخیره تغییرات</button>
              <button class="secondary-button" id="builderReload" type="button">↻ تازه‌سازی</button>
            </div>
          </div>
          <div class="builder-workspace">
            <aside class="builder-library panel-card">
              <div class="builder-panel-heading"><div><strong>کتابخانه بلوک‌ها</strong><small>برای افزودن به صفحه انتخاب کن</small></div></div>
              <div class="builder-library-tabs" role="tablist" aria-label="ابزارهای صفحه‌ساز">
                <button type="button" class="is-active" data-library-tab="elements">عناصر</button>
                <button type="button" data-library-tab="structure">ساختار</button>
              </div>
              <div class="builder-library-view" data-library-panel="elements">
                <div class="builder-library-list">
                  <div class="builder-library-group"><strong>چیدمان</strong><small>ساختار و ستون‌بندی</small></div>
                  <button class="builder-library-item" type="button" data-add-block="container"><b>▱</b><span>کانتینر<small>چیدمان تو در تو</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="grid"><b>▦</b><span>گرید<small>شبکه واکنش‌گرا</small></span></button>
                  <div class="builder-library-group"><strong>عناصر پایه</strong><small>متن و رسانه</small></div>
                  <button class="builder-library-item" type="button" data-add-block="heading"><b>H</b><span>عنوان<small>Heading</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="paragraph"><b>¶</b><span>متن<small>Paragraph</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="button"><b>↗</b><span>دکمه<small>پیوند و دعوت به اقدام</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="image"><b>▧</b><span>تصویر<small>از کتابخانه رسانه</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="card"><b>▣</b><span>کارت<small>محتوای مستقل</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="divider"><b>―</b><span>جداکننده<small>خط افقی</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="icon"><b>✦</b><span>آیکون<small>نماد متنی</small></span></button>
                  <div class="builder-library-group"><strong>عناصر پیشرفته</strong><small>اطلاعات و تعامل</small></div>
<button class="builder-library-item" type="button" data-add-block="feature"><b>✦</b><span>ویژگی<small>Feature Card</small></span></button>
<button class="builder-library-item" type="button" data-add-block="pricing"><b>＄</b><span>قیمت‌گذاری<small>Pricing Plans</small></span></button>
<button class="builder-library-item" type="button" data-add-block="accordion"><b>⌄</b><span>سؤالات متداول<small>FAQ Accordion</small></span></button>
<button class="builder-library-item" type="button" data-add-block="testimonial"><b>❝</b><span>نظر مشتری<small>Testimonials</small></span></button>
<button class="builder-library-item" type="button" data-add-block="gallery"><b>▧</b><span>گالری تصاویر<small>Gallery</small></span></button>
<button class="builder-library-item" type="button" data-add-block="timeline"><b>◷</b><span>خط زمانی<small>Timeline</small></span></button>
<button class="builder-library-item" type="button" data-add-block="skill"><b>▤</b><span>مهارت و پیشرفت<small>Progress</small></span></button>
<button class="builder-library-item" type="button" data-add-block="logo_cloud"><b>◎</b><span>لوگوها<small>Logo Cloud</small></span></button>
<button class="builder-library-item" type="button" data-add-block="team"><b>♙</b><span>اعضای تیم<small>Team</small></span></button>
<button class="builder-library-item" type="button" data-add-block="tabs"><b>▥</b><span>تب‌های محتوا<small>Tabs</small></span></button>
<button class="builder-library-item" type="button" data-add-block="newsletter"><b>✉</b><span>خبرنامه<small>Newsletter</small></span></button>
<button class="builder-library-item" type="button" data-add-block="icon_box"><b>✧</b><span>کارت آیکون‌دار<small>Icon Box</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="counter"><b>＋</b><span>شمارنده<small>عدد و برچسب</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="list"><b>☷</b><span>فهرست<small>موارد متنی</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="video"><b>▷</b><span>ویدیو<small>YouTube / Vimeo Embed</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="cta"><b>↗</b><span>دعوت به اقدام<small>متن و دکمه لینک‌دار</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="stats"><b>▥</b><span>آمار و اعداد<small>کارت‌های آماری</small></span></button>
                  <button class="builder-library-item" type="button" data-add-block="spacer"><b>↕</b><span>فاصله عمودی<small>تنظیم ریتم صفحه</small></span></button>
                </div>
              </div>
              <div class="builder-library-view" data-library-panel="structure" hidden>
                <div class="builder-panel-heading"><div><strong>Navigator · ساختار صفحه</strong><small>درخت عناصر، انتخاب، جست‌وجو و جابه‌جایی</small></div></div>
                <div class="builder-layers-tools">
                  <input id="builderLayerSearch" type="search" placeholder="جست‌وجوی نام یا نوع عنصر…" autocomplete="off">
                  <button type="button" id="builderLayerExpandAll" title="باز کردن همه">⌄</button>
                  <button type="button" id="builderLayerCollapseAll" title="بستن همه">⌃</button>
                </div>
                <div class="builder-layers-list" id="builderCanvas"><div class="builder-empty">در حال دریافت ساختار صفحه…</div></div>
              </div>
            </aside>
            <section class="builder-canvas-wrap panel-card">
              <div class="builder-canvas-header"><strong>پیش‌نمایش زنده سایت</strong><div class="builder-context-toolbar" id="builderContextToolbar" hidden><span id="builderContextLabel">عنصر انتخاب‌شده</span><button type="button" data-context-action="up" title="انتقال به بالا">↑</button><button type="button" data-context-action="down" title="انتقال به پایین">↓</button><button type="button" data-context-action="clone" title="تکثیر">＋</button><button type="button" data-context-action="toggle" title="نمایش یا مخفی‌کردن">◉</button><button type="button" data-context-action="delete" title="حذف">×</button></div><small>روی هر بخش کلیک کن تا انتخاب و ویرایش شود؛ تغییرات در همین نما دیده می‌شوند</small></div>
              <div class="builder-preview-frame-shell" id="builderPreviewShell" data-device="desktop">
                <iframe id="builderPreviewFrame" title="پیش‌نمایش زنده وب‌سایت" srcdoc="<?= h($builderPreviewDocument) ?>" loading="eager"></iframe>
                <?php if ($builderPreviewError !== ''): ?><p class="builder-preview-hint"><?= h($builderPreviewError) ?></p><?php endif; ?>
              </div>
            </section>
            <aside class="builder-inspector panel-card">
              <div class="builder-inspector-inner">
                <nav class="builder-inspector-tabs" role="tablist" aria-label="تنظیمات ویرایشگر">
                  <button type="button" class="is-active" data-inspector-tab="content">محتوا</button>
                  <button type="button" data-inspector-tab="style">استایل</button>
                  <button type="button" data-inspector-tab="advanced">پیشرفته</button>
                  <button type="button" data-inspector-tab="global">سایت</button>
                </nav>
                <div id="builderInspectorForm"><div class="builder-empty">یک بلوک را انتخاب کن.</div></div>
                <section class="builder-site-settings" data-inspector-panel="global" hidden>
                  <div class="builder-panel-heading"><div><strong>تنظیمات کلی سایت</strong><small>رنگ‌ها، عرض و فاصله‌بندی عمومی</small></div></div>
                  <form id="builderDesignForm" class="builder-form">
<label>پالت رنگ یکپارچه
                      <select name="color_palette">
                        <option value="violet">بنفش · کلاسیک</option>
                        <option value="ocean">آبی اقیانوسی</option>
                        <option value="emerald">سبز زمردی</option>
                        <option value="sunset">نارنجی غروب</option>
                        <option value="rose">رز و صورتی</option>
                        <option value="graphite">خاکستری گرافیتی</option>
                      </select>
                    </label>
                                        <label class="color-field">رنگ اصلی<input type="color" name="accent_color" value="#a58aff"></label>
                    <label class="color-field">پس‌زمینه صفحه<input type="color" name="page_background" value="#080b12"></label>
                    <label>عرض محتوا<input type="range" name="content_width" min="760" max="1600" step="20" value="1160"></label>
                    <label>گردی گوشه‌ها<input type="range" name="corner_radius" min="0" max="48" value="22"></label>
                    <label>مقیاس فونت<input type="range" name="font_scale" min="0.85" max="1.2" step="0.05" value="1"></label>
                    <label>فاصله سکشن‌ها<input type="range" name="section_spacing" min="32" max="180" step="4" value="72"></label>
                    <div class="builder-form-actions"><button class="primary-button compact" type="submit">ذخیره ظاهر کلی</button></div>
                  </form>
                </section>
              </div>
            </aside>
          </div>
        </main>
  <div class="builder-media-modal" id="builderMediaModal" hidden>
    <section class="builder-media-dialog" role="dialog" aria-modal="true" aria-labelledby="builderMediaTitle">
      <header><div><span class="section-kicker">MEDIA LIBRARY</span><h3 id="builderMediaTitle">کتابخانه رسانه</h3></div><button type="button" data-media-close aria-label="بستن">×</button></header>
      <form id="builderMediaUpload"><label class="builder-media-upload">بارگذاری تصویر یا ویدیو<input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" required></label><button class="primary-button compact" type="submit">بارگذاری</button><span id="builderMediaStatus"></span></form>
      <div class="builder-media-grid" id="builderMediaGrid"><p class="builder-empty">در حال دریافت فایل‌ها…</p></div>
    </section>
  </div>
  <div class="builder-template-modal" id="builderTemplateModal" hidden>
<section class="builder-template-dialog studio-template-dialog" role="dialog" aria-modal="true" aria-labelledby="builderTemplateTitle">
<header><div><span class="section-kicker">MILAD STUDIO LIBRARY</span><h3 id="builderTemplateTitle">کتابخانه قالب‌ها</h3><p>قالب‌های آماده را بر اساس کاربرد و سبک بصری پیدا کن و به صفحه اضافه کن.</p></div><button type="button" data-template-close aria-label="بستن">×</button></header>
<div class="studio-template-tools"><input id="builderTemplateSearch" type="search" placeholder="جست‌وجوی قالب‌ها…">
<div class="studio-template-filters" role="group" aria-label="فیلتر دسته‌بندی قالب‌ها">
<button type="button" class="is-active" data-template-filter="all">همه</button><button type="button" data-template-filter="full">صفحات کامل</button><button type="button" data-template-filter="hero">معرفی</button><button type="button" data-template-filter="business">کسب‌وکار</button><button type="button" data-template-filter="content">محتوا</button><button type="button" data-template-filter="personal">شخصی</button>
</div></div>
<div class="builder-template-grid studio-template-grid" id="builderTemplateGrid"></div>
<p class="studio-template-footnote">قالب‌های سکشن یا صفحه کامل به انتهای صفحه اضافه می‌شوند؛ محتوای قبلی حذف نمی‌شود.</p>
</section></div>
<div class="toast-region" id="toastRegion" aria-live="polite"></div>
</body></html>