# Milad CMS

Installable modular PHP CMS for the Milad personal website and future portfolio-style websites.

## Implemented

- PHP 8.1+ front controller and PDO MySQL database layer
- Browser installation wizard at `/install`, including deployment under a subdirectory such as `/site/install`
- File Manager activation gate for first installation
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

PHP 8.1+, MySQL 8 or MariaDB 10.5+, PDO, pdo_mysql, fileinfo, DOM, JSON, OpenSSL and mbstring. Apache hosting must allow `.htaccess` and have `mod_rewrite` enabled.

## Installation in a subdirectory (example: /site)

1. Upload and extract the branch ZIP contents directly into `public_html/site/` (not into an additional nested directory).
2. Keep the project-root `.htaccess` and the `public/` directory. The root rewrite serves `public/index.php` while keeping application internals blocked.
3. Create a MySQL/MariaDB database and dedicated user in the hosting control panel. Grant SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX and REFERENCES.
4. In `public_html/site/storage/`, create `install.enabled` containing exactly `MILAD-CMS-ALLOW-FIRST-INSTALL`.
5. Ensure `config/` and `storage/` are writable by PHP during installation.
6. Visit `https://your-domain.example/site/install`. Set Site URL to `https://your-domain.example/site`, then complete the wizard.
7. Sign in at `/site/login`; dashboard is at `/site/admin`.

For a root-domain deployment, point the domain Document Root directly to the project's `public/` directory and use `https://your-domain.example/install`.

## Scope

The visual builder controls homepage section order and visibility; it is not yet a free-form nested page builder. Full user/role management UI, taxonomy, scheduled publishing, backup/restore, GitHub synchronization and a public versioned REST API remain follow-on work.

## Security

Uploads are stored in `storage/uploads` outside the public document root. SVG and executable uploads are rejected. Use HTTPS, a dedicated database user and strong unique passwords. Back up the database and uploads before upgrades.
