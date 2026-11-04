# Integrated Hotel Management System (IHMS)

PHP 8.1+ · MySQL/MariaDB · XAMPP. One central database and shared JSON API for the integrated departments.
**Scope delivered:** the **central database for all nine departments**, the **Admin department**, and integrated Security workflows. Security uses the same PHP application, MySQL database, sign-in, permissions, and shared interface as the other departments.

## Install on XAMPP (Windows)

1. Install XAMPP (PHP 8.1 or newer). In the XAMPP Control Panel, start **Apache** and **MySQL**.
2. Copy this folder to `C:\xampp\htdocs\hotel-management-system`.
3. Copy `.env.example` to `.env` and review it. XAMPP defaults (`DB_USER=root`, empty `DB_PASS`) work for local development only.
4. Open a terminal in the project folder and run (XAMPP's PHP is at `C:\xampp\php\php.exe`):
   ```
   C:\xampp\php\php.exe database\migrate.php migrate
   C:\xampp\php\php.exe database\migrate.php seed
   ```
   C:\xampp\php\php.exe database\create-admin.php
   ```
   `create-admin.php` asks for the first administrator's username, email, name and a temporary password (typed hidden, stored only as a hash). That account must choose a new password at first sign-in. It refuses to run again once an administrator exists.
   ```
   The first two commands create the `ihms` database (utf8mb4), applies all migrations, and seeds the nine departments and default settings. Both commands are safe to re-run.
5. Make sure Apache has `mod_rewrite` enabled (default in XAMPP) and `AllowOverride All` for htdocs (default).
6. Open `http://localhost/hotel-management-system/` and sign in. API health check: `http://localhost/hotel-management-system/api/v1/health`.
7. Run the tests: `C:\xampp\php\php.exe tests\run.php`.

The root `.htaccess` routes every request into `public/`, so `app/`, `config/`, `storage/`, `database/` and `.env` can never be fetched by URL.

## Commands

| Command | Purpose |
|---|---|
| `php database/migrate.php migrate` | Create DB if missing, apply pending migrations |
| `php database/migrate.php seed` | Idempotent seed data |
| `php database/migrate.php status` | Applied / pending |
| `php database/migrate.php fresh --confirm=<dbname>` | **Dev only.** Drops all tables and rebuilds. Refuses unless `APP_ENV` is `local` or `testing`. |
| `php database/seed-demo.php` | **Optional.** Fills the hotel with a sample month so the dashboard analytics have something to show. Every row is prefixed `DM-`. `php database/seed-demo.php --status` shows the row counts. |
| `php tests/run.php` | Run the test suite (no Composer needed) |

## The central database
123 application tables in 8 groups (A access control · B guests and accommodation · C billing and finance · D food and beverage · E kitchen and inventory · F housekeeping · G maintenance · H security), 286 foreign keys, CHECK rules, unique idempotency keys, and 58 triggers that make ledgers and history append-only. Documentation: `docs/data-dictionary.md`, `docs/erd/ihms-erd.md` (diagrams in `docs/erd/images/`), regenerate with `php database/tools/generate-docs.php`. Browse it in the app under **Admin > Central database**.

## What the Admin department includes
Sign-in/out with session regeneration, idle timeout, lockout (5 failures / 15 min, configurable) and per-IP throttling; CSRF protection; password reset (local mail outbox at `storage/private/mail-outbox.log` instead of SMTP); 65 permissions and 9 department roles; Admin dashboard (employees, central database overview, departments, roles & permissions, settings, audit log, login activity, sessions, system errors, approvals, notifications, backups); notification centre for every user. API reference: `docs/api.md`.

## Security department
Security staff sign in through the normal IHMS login. Their dashboard and workflows run in the PHP application and use the shared MySQL database, PHP JSON API, role permissions, and shared HTML/CSS interface. The compact Security navigation includes incidents, visitors, guard shifts and handovers, patrols, restricted CCTV references, and safety inspections. CCTV setup can enumerate and preview cameras visible to the current browser after the user grants permission; saved camera names and locations are stored in the shared database. Video preview is local and is not recorded or uploaded. Remote phones and network cameras cannot be automatically discovered by a browser. Account requests go through the IHMS administrator approval queue.

The Security API is protected by authentication, per-feature permissions, and CSRF checks for writes. Its records use the central Security tables in migrations `008_security.sql` and the shared user/session system. Migration `013_security_app_central.sql` remains in the migration history; it is not required to run a separate Security server.

## Analytics and the interface
The **Admin dashboard** and the **Admin → Analytics** page are driven by one read-only endpoint, `GET /api/v1/admin/analytics?days=7|14|30|90` (permission `admin.dashboard`). It computes occupancy, revenue, front-office flow, department work queues, sign-in activity and audit activity live from the central database — nothing is cached and nothing is written.

The interface uses one shared stylesheet, `public/assets/css/app.css`, themed in **dusty pink, khaki and cream**. The charts are dependency-free inline SVG drawn by `public/assets/js/charts.js` (line/area, column, donut, sparkline and bar lists), so there is no build step and no third-party script.

To see the analytics with data, run `php database/seed-demo.php`. The ledgers are append-only, so demo rows cannot be deleted afterwards — rebuild with `php database/migrate.php fresh --confirm=ihms` to get a clean database.

Backups use `mysqldump`. If it is not on the PATH, set `DB_DUMP_BIN=C:\xampp\mysql\bin\mysqldump.exe` in `.env`. Restore: `C:\xampp\mysql\bin\mysql.exe -u root ihms < storage\private\backups\<file>.sql`.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `Connection refused` / `Database error` | Start MySQL in XAMPP; check `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` in `.env` |
| `404` on `/api/v1/...` | Enable `mod_rewrite`; confirm `.htaccess` files were copied (they are hidden files) |
| `403 Forbidden` on the site root | Folder must be directly under `htdocs`; Apache needs `AllowOverride All` |
| `php is not recognized` | Use the full path `C:\xampp\php\php.exe` |
| Locked out of your own admin account | Another admin can unlock you; otherwise run `UPDATE users SET locked_until=NULL WHERE username='…';` in phpMyAdmin |
| Reset email never arrives | Expected locally: open `storage/private/mail-outbox.log` and copy the link |
| `500` with an `error_id` | Search `storage/logs/app-YYYY-MM-DD.log` for that id |

## Layout

`app/Core` (router, request/response, database, logger, error handler) · `app/Controllers` · `app/Services` · `app/Repositories` · `app/Middleware` · `config/` · `database/migrations` · `database/seeds` · `routes/api.php` · `public/` (only web-visible folder) · `storage/` (logs, private files) · `tests/`. Runtime and test code are PHP; frontend assets are HTML, CSS, and JavaScript.
