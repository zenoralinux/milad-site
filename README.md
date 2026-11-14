# وب‌سایت شخصی میلاد

وب‌سایت شخصی فارسی با طراحی مینیمال لوکس و آینده‌نگر، همراه با CMS اختصاصی PHP برای مدیریت محتوای صفحه اصلی و پروژه‌ها.

## قابلیت‌ها

### وب‌سایت عمومی
- حفظ رابط کاربری اصلی، پوسته روشن/تیره و طراحی واکنش‌گرا
- نمایش پویا و Server-Side از محتوای صفحه اصلی
- دریافت پروژه‌ها، وضعیت، دسته‌بندی، فناوری‌ها و لینک‌ها از دیتابیس
- مخفی‌سازی بخش‌ها و پروژه‌های پیش‌نویس
- تنظیم عنوان، توضیحات SEO، Canonical و Open Graph از پنل
- در زمان پیکربندی نشدن PHP/دیتابیس، فایل استاتیک index.html به‌عنوان fallback حفظ می‌شود

### پنل مدیریت
- ورود امن مدیر در مسیر /admin
- داشبورد با آمار واقعی پروژه‌ها و تاریخچه رویدادها
- ویرایش بخش‌های Hero، معرفی، پروژه‌ها، درباره من، اصول کاری، اکنون، ارتباط و Footer
- ایجاد، ویرایش، حذف، انتشار و مخفی کردن پروژه‌ها
- مدیریت دسته‌بندی، وضعیت، قالب نمایشی، لینک‌ها، فناوری‌ها و ترتیب نمایش
- تنظیم عنوان سایت، SEO، تصویر پروفایل و لینک‌های GitHub/Telegram
- رابط واکنش‌گرا برای موبایل و دسکتاپ

### امنیت
- password_hash و password_verify
- PHP Session با کوکی HttpOnly و SameSite
- CSRF Token برای عملیات تغییردهنده
- محدودیت ۵ تلاش ناموفق ورود در ۱۵ دقیقه برای هر ترکیب حساب/IP
- PDO Prepared Statements
- اعتبارسنجی سمت سرور و Escape خروجی HTML
- ثبت فعالیت‌های مدیریتی
- عدم وجود ثبت‌نام عمومی مدیر

## پیش‌نیازها

- PHP 8.2 یا جدیدتر
- MySQL 8+ یا MariaDB 10.6+
- افزونه‌های PHP: pdo_mysql و mbstring (در نبود mbstring، fallback متنی داخلی استفاده می‌شود)
- Apache با mod_rewrite و اجازه استفاده از .htaccess، یا تنظیم معادل در Nginx
- HTTPS در محیط Production

## نصب روی هاست PHP

1. کل پروژه را در هر مسیر دلخواه روی هاست قرار بده؛ برای نمونه در ریشه دامنه، /public_html/ یا /public_html/portfolio/. در Apache، Document Root باید روی پوشه پروژه باشد.
2. یک دیتابیس و کاربر اختصاصی MySQL بساز و دسترسی این کاربر را به همان دیتابیس محدود کن.
3. فایل config.example.php را به config.php یا config.local.php کپی کن. نام دیتابیس و نام کاربری از قبل روی zenorali_milad تنظیم شده‌اند؛ میزبان دیتابیس را در صورت نیاز با مقدار هاست تغییر بده.
4. رمز دیتابیس را در متغیر محیطی MILAD_DB_PASSWORD یا در فایل محلی config.php/config.local.php قرار بده. فایل تنظیمات واقعی در .gitignore است؛ هرگز آن را Commit نکن.
5. مقدار app.ip_hash_key را با یک Secret تصادفی طولانی جایگزین کن. app.base_url را خالی بگذار تا دامنه تشخیص داده شود و app.base_path را هم خالی بگذار تا مسیر ریشه یا زیرپوشه خودکار تشخیص داده شود.
6. از ریشه پروژه اجرا کن:

    php bin/install.php

نصب‌کننده جداول را ایجاد می‌کند، بخش‌های اولیه و پنج پروژه فعلی را Seed می‌کند و نام کاربری/رمز مدیر اصلی را از ورودی ترمینال می‌گیرد. نصب مجدد داده‌های موجود را بازنویسی نمی‌کند و پروژه‌های اولیه فقط وقتی جدول پروژه خالی باشد Seed می‌شوند.

7. آدرس مدیریت به‌صورت خودکار ساخته می‌شود: اگر پروژه در ریشه نصب شده باشد /admin و اگر در /portfolio قرار گرفته باشد /portfolio/admin.
8. در پنل، محتوای سایت و پروژه‌ها را بررسی و ذخیره کن.

**هرگز config.php یا config.local.php را Commit نکن.** هر دو در .gitignore قرار دارند و Apache نیز دسترسی مستقیم به آن‌ها را مسدود می‌کند. امن‌ترین حالت این است که فایل تنظیمات خارج از Document Root باشد و مسیر کامل آن را با MILAD_SITE_CONFIG مشخص کنی.

### نصب در هر مسیر
- مسیر نصب از SCRIPT_NAME تشخیص داده می‌شود؛ ریشه دامنه، یک زیرپوشه یا مسیر چندسطحی پشتیبانی می‌شود.
- لینک‌های CSS/JS پنل، API، خروج از حساب، لینک‌های داخلی و Canonical بر اساس محل واقعی نصب ساخته می‌شوند.
- اگر سایت پشت Reverse Proxy در یک Prefix منتشر شود و وب‌سرور آن Prefix را به PHP منتقل نکند، مقدار app.base_path یا MILAD_APP_BASE_PATH را صریحاً مانند /portfolio تنظیم کن.
- فایل تنظیمات می‌تواند بیرون از پروژه و در هر مسیر سرور باشد؛ مسیر مطلق آن را در MILAD_SITE_CONFIG قرار بده.

## تنظیم با Environment Variables

به‌جای فایل config.php می‌توانی متغیرهای زیر را در محیط اجرا تعریف کنی:

| متغیر | توضیح |
|---|---|
| MILAD_DB_HOST | میزبان دیتابیس |
| MILAD_DB_PORT | پورت دیتابیس؛ پیش‌فرض 3306 |
| MILAD_DB_NAME | نام دیتابیس |
| MILAD_DB_USER | کاربر دیتابیس |
| MILAD_DB_PASSWORD | رمز دیتابیس |
| MILAD_APP_BASE_URL | آدرس Origin سایت، بدون مسیر زیرپوشه؛ در صورت خالی بودن از درخواست فعلی تشخیص داده می‌شود |
| MILAD_APP_BASE_PATH | Prefix نصب مثل /portfolio؛ در حالت عادی خالی و خودکار تشخیص داده می‌شود |
| MILAD_APP_TIMEZONE | منطقه زمانی؛ پیش‌فرض Europe/Berlin |
| MILAD_APP_IP_HASH_KEY | Secret مستقل برای Hash کردن IP |
| MILAD_APP_DEBUG | فقط برای محیط توسعه؛ در Production خاموش باشد |

بعد از تعریف متغیرها، نصب را از CLI اجرا کن.

## اجرای محلی با Docker

این مخزن یک Dockerfile مبتنی بر Apache/PHP دارد. قبل از اجرا، متغیرهای دیتابیس را تنظیم کن و مطمئن شو دیتابیس از کانتینر قابل دسترسی است.

    docker build -t milad-site-cms .
    docker run --rm -p 8080:80 \
      -e MILAD_DB_HOST=host.docker.internal \
      -e MILAD_DB_PORT=3306 \
      -e MILAD_DB_NAME=zenorali_milad \
      -e MILAD_DB_USER=zenorali_milad \
      -e MILAD_DB_PASSWORD='your-secret' \
      -e MILAD_APP_BASE_URL=http://localhost:8080 \
      -e MILAD_APP_IP_HASH_KEY='replace-with-a-long-random-secret' \
      milad-site-cms

سپس در کانتینر یا محیط PHP اجرا کن:

    php bin/install.php

آدرس‌ها:
- سایت: http://localhost:8080/
- مدیریت: http://localhost:8080/admin

برای Render یا هر سرویس مشابهی که پروژه فعلی را به‌صورت Static Site منتشر می‌کند، باید نوع سرویس به Web Service مبتنی بر Docker/PHP تغییر کند و یک MySQL/MariaDB قابل دسترس از آن سرویس فراهم شود. Static hosting به‌تنهایی PHP را اجرا نمی‌کند.

## ساختار مهم

    app/bootstrap.php       # اتصال دیتابیس، Session، CSRF و توابع پایه
    admin/index.php         # رابط پنل مدیریت
    api/admin.php           # API احراز هویت‌شده
    assets/css/admin.css    # طراحی پنل
    assets/js/admin.js      # تعاملات پنل
    bin/install.php         # نصب Schema و ساخت مدیر اولیه
    database/schema.sql    # ساختار دیتابیس
    docs/ADMIN-CMS-TECHNICAL-PLAN.md
    index.html              # نسخه استاتیک fallback
    index.php               # رندر پویا از دیتابیس
    config.example.php      # نمونه تنظیمات

## مسیرهای اصلی

- /: صفحه اصلی پویا
- /admin: پنل مدیریت
- /api/admin.php?action=...: API خصوصی پنل

## نکات مهاجرت و انتشار

- در محیطی که PHP اجرا نمی‌شود، نسخه قدیمی استاتیک همچنان از index.html قابل مشاهده است.
- در Apache، فایل .htaccess، index.php را قبل از index.html به‌عنوان Directory Index تنظیم می‌کند.
- در Nginx باید معادل Rewrite و PHP-FPM جداگانه تنظیم شود.
- قبل از انتشار، از فایل‌ها و دیتابیس نسخه پشتیبان بگیر.
- نسخه اول شامل CMS پایه و مدیریت پروژه/محتوای صفحه است. وبلاگ، صندوق پیام‌ها، Media Library، Analytics و همگام‌سازی GitHub در مراحل بعدی قابل اضافه شدن هستند.

## لینک‌ها

- وب‌سایت: https://miladalizadw.ir
- Zenora Linux: https://zenoralinux.ir
- GitHub: https://github.com/miladalizadw
- Telegram: https://t.me/miladalizadw

> رمز اتصال دیتابیس عمداً در Repository قرار داده نمی‌شود. مقدار آن را در Secret/Environment هاست یا فایل config.local.php که Git آن را نادیده می‌گیرد تنظیم کن.
