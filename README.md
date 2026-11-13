# Milad CMS

Milad CMS is an installable modular PHP CMS for the Milad personal website and future portfolio-style websites.

## Implemented in this branch

- PHP 8.1+ front controller and PDO MySQL database layer
- Browser installer at \`/install\`
- First owner account, secure password hashing and login
- Session regeneration, CSRF validation and permission checks
- Admin dashboard and activity log
- Pages CRUD, draft/published state and revision snapshots
- Portfolio projects CRUD
- Media library with private file storage and controlled image delivery
- Site identity and homepage settings
- Server-rendered Persian RTL homepage and page templates
- Local CSS/JS; no CDN or Node build requirement on production

## Requirements

PHP 8.1+, MySQL 8 or MariaDB 10.5+, PDO, pdo_mysql, fileinfo, DOM, JSON, OpenSSL and mbstring.

## Installation

1. Create a MySQL/MariaDB database and a dedicated database user. Grant privileges on that database: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX and REFERENCES.
2. Upload the repository to the host.
3. Set the domain Document Root to the repository's \`public/\` directory. Do not expose the repository root.
4. Ensure \`config/\` and \`storage/\` are writable by PHP during installation.
5. Visit \`https://your-domain.example/install\`.
6. Enter database details, site URL/timezone and the first owner account.
7. Sign in at \`/login\`; the dashboard is at \`/admin\`.
8. Keep \`config/local.php\`, \`storage/install.lock\` and \`storage/uploads\` private. Never commit them.

The installer creates tables, the first owner, \`config/local.php\` and \`storage/install.lock\`. It refuses to run after successful installation.

## Local development

From the repository root:

\`\`\`bash
php -S 127.0.0.1:8000 -t public public/index.php
\`\`\`

Create an empty database first, then visit \`http://127.0.0.1:8000/install\`.

Check the PHP environment:

\`\`\`bash
php bin/verify.php
\`\`\`

## Nginx

Set root to \`/var/www/milad-site/public\`, then route unknown paths to \`index.php\`:

\`\`\`nginx
root /var/www/milad-site/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \\.php$ {
  include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ /\\. { deny all; }
\`\`\`

Change the PHP-FPM socket to match the host.

## Scope

This is the first installable CMS foundation. The rich page editor supports common formatting and server-side HTML sanitization. A full drag-and-drop visual page builder, user/role management UI, taxonomy, scheduled publishing, backup/restore, GitHub synchronization and a public versioned REST API remain follow-on work and are not claimed as implemented.

## Security

Uploads are stored in \`storage/uploads\` outside the public document root. SVG and executable uploads are rejected. Use HTTPS, a dedicated DB user and strong unique passwords. Back up the database and uploads before upgrades.
