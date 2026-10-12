# Repository Guidelines

## Project Structure & Module Organization

Life Atlas is a personal growth and journaling application built with Laravel 12, PHP 8.2+, Livewire 3/Volt, and MariaDB.

- `app/` contains Eloquent models, HTTP controllers, Livewire components, and queued jobs such as `CreateBackup`.
- `resources/views/` contains Blade templates; Livewire views live in `resources/views/livewire/`. Frontend entry points are in `resources/css/` and `resources/js/`, using Tailwind CSS and Vite.
- `routes/` defines web, authentication, and console routes. `database/` contains migrations, factories, and seeders.
- `tests/Feature/` covers application behavior; `tests/Unit/` contains isolated tests. `public/` serves assets; `storage/` holds runtime files.

## Build, Test, and Development Commands

Follow `README.md` for initial environment and Docker setup.

- `docker compose up -d`: start the Sail application and supporting services.
- `docker compose exec laravel.test php artisan migrate --seed`: apply migrations and seed initial life areas.
- `docker compose exec laravel.test npm run dev`: start Vite for frontend development.
- `docker compose exec laravel.test npm run build`: compile production assets.
- `docker compose exec laravel.test composer test`: clear cached configuration and run the test suite.
- `docker compose exec laravel.test php artisan test --filter=DailyBackupTest`: run focused backup tests.

With local dependencies installed, `composer dev` starts the application server, queue listener, log viewer, and Vite together.

## Coding Style & Naming Conventions

Follow `.editorconfig`: UTF-8, LF endings, four-space indentation, and a final newline. YAML uses two spaces except `compose.yaml`, which uses four. Use PascalCase PHP classes, camelCase methods, snake_case database fields, and kebab-case Blade filenames. Keep namespaces aligned with directories. Laravel Pint is available; run `vendor/bin/pint --dirty` inside the application container to format changed PHP files.

## Testing Guidelines

Use PHPUnit 11, `*Test.php` filenames, and descriptive `test_...` methods. Tests use in-memory SQLite. Use `RefreshDatabase` for database-backed tests and Laravel/Livewire testing helpers for component behavior. Fake queued work when testing dispatch. Add regression coverage for behavior changes; no minimum coverage percentage is configured.

## Commit & Pull Request Guidelines

Recent commits use short, lowercase summaries such as `added database backup feature`; follow that style and keep changes focused. PRs should explain the change, list validation commands and results, link relevant issues, and include screenshots for UI changes. Mention migration or configuration requirements.

## Security & Configuration

Keep secrets in `.env`; document new settings in `.env.example`. Never commit credentials or database dumps. Backups use the dedicated `backups` disk rooted at `/var/lib/lifeatlas`, with an external host directory mounted at `/var/lib/lifeatlas/backups` in both application and queue containers. Leave `BACKUP_HOST_PATH` empty in `.env.example`; document generic paths without personal usernames. Users must set an absolute external path explicitly and create the host directory manually before starting Docker. Backups remain private and local. Never fall back to repository storage or migrate existing backups automatically. Windows and Docker Desktop macOS use `BACKUP_PERMISSION_MODE=host` and restricted host access; native Linux can use `posix` for directory permissions 0700 when the worker can enforce them.

Backup storage tests extend `Tests\Support\IsolatedBackupTestCase`: they bypass the real `.env` and cached configuration, use in-memory SQLite and unique temporary storage, and fake dump processes. Run only `BackupRuntimeTest|BackupStorageTest` when real data must not be accessed. Do not restart services, run real dumps, or migrate backup files as part of these tests.
