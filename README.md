# Milad CMS

Installable modular PHP CMS for the Milad personal website and future portfolio-style websites.

## Implemented in this branch

- PHP 8.1+ front controller and PDO MySQL database layer
- Browser installation wizard at `/install` (no SSH or terminal command required)
- File Manager activation gate to prevent an uninitialized public installer from being claimed
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

## Installation without SSH

1. Create a MySQL/MariaDB database and dedicated user in the hosting control panel. Grant SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX and REFERENCES on that database.
2. Upload the repository and set the domain Document Root to its `public/` directory. Do not expose the repository root.
3. In the hosting File Manager, open the project's `storage/` directory and create a file named `install.enabled`.
4. Put exactly this text inside the file: `MILAD-CMS-ALLOW-FIRST-INSTALL`. Save it. Do not put this file inside `public/`.
5. Ensure `config/` and `storage/` are writable by PHP during installation.
6. Visit `https://your-domain.example/install`, refresh the page if needed, and complete the graphical wizard: database details, site URL/timezone and the first owner account.
7. Sign in at `/login`; the dashboard is at `/admin`.

The installer verifies the activation file, creates the tables and owner account, writes `config/local.php` and creates `storage/install.lock`. On successful installation, it removes `storage/install.enabled` and refuses to run again. Never add the activation file to Git or leave it in place after installation.

## Scope

The visual builder currently controls homepage section order and visibility; it is not yet a free-form nested page builder. Full user/role management UI, taxonomy, scheduled publishing, backup/restore, GitHub synchronization and a public versioned REST API remain follow-on work.

## Security

Uploads are stored in `storage/uploads` outside the public document root. SVG and executable uploads are rejected. Use HTTPS, a dedicated database user and strong unique passwords. Back up the database and uploads before upgrades.
