# Milad CMS

Installable modular PHP CMS for the Milad personal website and future portfolio-style websites.

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
- Section-based visual homepage builder with drag-and-drop ordering and visibility toggles
- Rich page content editor with server-side HTML sanitization
- Server-rendered Persian RTL homepage and page templates

## Requirements

PHP 8.1+, MySQL 8 or MariaDB 10.5+, PDO, pdo_mysql, fileinfo, DOM, JSON, OpenSSL and mbstring.

## Installation

1. Create a MySQL/MariaDB database and dedicated user. Grant SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX and REFERENCES on that database.
2. Upload the repository and set the domain Document Root to its \`public/\` directory. Do not expose the repository root.
3. Ensure \`config/\` and \`storage/\` are writable by PHP during installation.
4. Visit \`https://your-domain.example/install\`, enter database details, site URL/timezone and the first owner account.
5. Sign in at \`/login\`; the dashboard is at \`/admin\`. Keep \`config/local.php\`, \`storage/install.lock\` and \`storage/uploads\` private.

The installer creates database tables, the first owner, configuration and the install lock. It refuses to run after successful installation.

## Local development

\`\`\`bash
php -S 127.0.0.1:8000 -t public public/index.php
\`\`\`

Create an empty database first, then visit \`http://127.0.0.1:8000/install\`. Run \`php bin/verify.php\` for environment checks.

## Nginx

Set root to \`/var/www/milad-site/public\`, then use \`try_files $uri $uri/ /index.php?$query_string;\` and configure PHP-FPM for \`index.php\`.

## Scope

The visual builder currently controls homepage section order and visibility; it is not yet a free-form nested page builder. Full user/role management UI, taxonomy, scheduled publishing, backup/restore, GitHub synchronization and a public versioned REST API remain follow-on work.

## Security

Uploads are stored in \`storage/uploads\` outside the public document root. SVG and executable uploads are rejected. Use HTTPS, a dedicated database user and strong unique passwords. Back up the database and uploads before upgrades.
