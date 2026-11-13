# راهنمای نصب و استقرار Milad CMS

## پیش‌نیازها

- PHP 8.1 یا جدیدتر
- MySQL 8 یا MariaDB 10.5+
- افزونه‌های PDO، pdo_mysql، fileinfo، DOM، JSON، OpenSSL و mbstring
- Apache با mod_rewrite یا Nginx و SSL فعال

## نصب

1. در کنترل‌پنل هاست یک دیتابیس و کاربر اختصاصی بساز و دسترسی‌های SELECT، INSERT، UPDATE، DELETE، CREATE، ALTER، INDEX و REFERENCES را بده.
2. مخزن را آپلود کن و Document Root دامنه را روی پوشه \`public\` قرار بده. ریشه مخزن نباید از وب قابل دسترسی باشد.
3. دسترسی نوشتن موقت برای PHP به پوشه‌های \`config\` و \`storage\` بده.
4. از SSH یا ترمینال هاست، در ریشه پروژه اجرا کن:

\`\`\`bash
php bin/create-install-token.php
\`\`\`

5. توکن چاپ‌شده را کپی کن. این توکن فقط به‌شکل hash در \`storage\` ذخیره می‌شود و پس از نصب موفق حذف خواهد شد.
6. آدرس \`https://دامنه-تو/install\` را باز کن و توکن، اطلاعات دیتابیس، عنوان و URL سایت، منطقه زمانی و حساب مالک را وارد کن.
7. پس از نصب به \`/login\` منتقل می‌شوی؛ پنل مدیریت در \`/admin\` قرار دارد.

در صورت نبود دسترسی SSH، می‌توانی متغیر محیطی \`MILAD_INSTALL_TOKEN\` را در تنظیمات هاست تعریف کنی.

## پس از نصب

- فایل \`config/local.php\`، پوشه \`storage\` و فایل \`storage/install.lock\` را خصوصی نگه دار.
- قفل نصب را حذف نکن.
- HTTPS را اجباری کن و از دیتابیس و \`storage/uploads\` نسخه پشتیبان بگیر.

## اجرای محلی

\`\`\`bash
php bin/create-install-token.php
php -S 127.0.0.1:8000 -t public public/index.php
\`\`\`

سپس \`http://127.0.0.1:8000/install\` را باز کن. دیتابیس باید از قبل ساخته شده باشد.
