# Phase 1 — Laravel backend foundation

The existing portfolio pages in the parent directory remain unchanged. This backend is a separate Laravel application; moving the public design into Blade is Phase 2.

## Installed foundation

- Laravel 13 and Filament 5, with exact dependency versions recorded in `composer.lock`.
- A branded admin login at `/admin/login` and an authenticated dashboard at `/admin`.
- Explicit `is_admin` authorization, including in the local environment. New users are not admins, and the flag is not mass assignable.
- No public registration and no seeded/default login credentials.
- An interactive administrator creation command with hidden password and confirmation prompts.
- Database-backed encrypted sessions, cache, and jobs.
- Local SQLite database, an application encryption key, and Asia/Dhaka timezone.
- A production MySQL configuration example in [.env.mysql.example](./.env.mysql.example).

## Start locally on this Windows computer

From the original portfolio folder:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\backend.ps1 admin
powershell -NoProfile -ExecutionPolicy Bypass -File .\backend.ps1 serve
```

The first command creates your admin account. Use a unique password with at least 12 characters, uppercase and lowercase letters, a number, and a symbol. Passwords are entered in hidden terminal prompts, not passed as command arguments. An existing account is never overwritten or silently promoted.

Visit `http://127.0.0.1:8000/admin`. The local server is bound to loopback only. Stop it with Ctrl+C.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\backend.ps1 test
powershell -NoProfile -ExecutionPolicy Bypass -File .\backend.ps1 migrate
powershell -NoProfile -ExecutionPolicy Bypass -File .\backend.ps1 status
```

The launcher uses the project-local PHP runtime in `../.tools/php/`; it does not require global PHP or Composer installation. The runtime and SQLite database are local development files and excluded from version control. The runtime configuration uses this machine's absolute workspace path.

## On another machine

Install PHP 8.3+ with Laravel/Filament's required extensions and Composer. Then, from this backend directory:

```text
composer install
```

Copy `.env.example` to `.env`, choose SQLite or configure MySQL, and create an empty SQLite database file if using SQLite. Then:

```text
php artisan key:generate
php artisan migrate
php artisan filament:assets
php artisan portfolio:admin
php artisan serve --host=127.0.0.1 --port=8000
```

Phase 1 uses Filament's published assets and does not require a Node build. Later public frontend work can add a build pipeline if needed.

## Database and deployment notes

Local SQLite makes the backend testable without installing a MySQL service. Tests use a separate in-memory SQLite database. No production database is created or connected in Phase 1.

For production, use `.env.mysql.example` as a starting point and configure your actual database credentials, application URL, HTTPS session settings, and mail provider. Generate a fresh production application key and keep it stable after launch. Do not commit environment files, passwords, or SQLite data. Only `public/` should be web-accessible.

Email delivery and password reset are not enabled yet. Mail defaults to local logging; contact notifications belong to Phase 5. Content management resources belong to Phase 3. The default Filament user command creates a regular user without admin permission; use `portfolio:admin` for the administrator.

## Verification

Feature tests cover login visibility, guest redirects, disabled registration, admin authorization, valid/invalid login, logout, protected admin flags, password validation, and explicit administrator creation. The default seeder creates no accounts.
