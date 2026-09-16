# Life Atlas

Life Atlas is a personal growth and journaling platform that helps users track different areas of their lives through notes, metrics, and visual progress.

## Tech Stack

* Laravel 12
* Laravel Livewire
* MariaDB
* Laravel Sail
* Docker

## Requirements

* Docker Desktop
* Git

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/M-Araujo/LifeAtlas.git
cd LifeAtlas
```

### 2. Install PHP dependencies

If Composer is available locally:

```bash
composer install
```

Otherwise, dependencies can be installed through the Laravel Sail container after Docker is started.

### 3. Create the environment file

```bash
cp .env.example .env
```

On Windows PowerShell, you can also use:

```powershell
Copy-Item .env.example .env
```

Make sure the database configuration uses the Docker service:

```env
DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=life_atlas
DB_USERNAME=sail
DB_PASSWORD=password
```

### 4. Start Laravel Sail / Docker

Start the containers:

```bash
docker compose up -d
```

Check that they are running:

```bash
docker compose ps
```

The Laravel application should be available on:

```text
http://localhost
```

### 5. Generate the application key

Run Artisan through the Laravel container:

```bash
docker compose exec laravel.test php artisan key:generate
```

### 6. Fix Laravel storage permissions

Laravel needs write access to `storage` and `bootstrap/cache`.

```bash
docker compose exec laravel.test chown -R sail:sail storage bootstrap/cache
```

### 7. Run migrations and seed the database

```bash
docker compose exec laravel.test php artisan migrate --seed
```

This runs the migrations and the `DatabaseSeeder`, which seeds the initial life areas.

### 8. Build Vite assets

For the local production version, compile the frontend assets:

```bash
docker compose exec laravel.test npm run build
```

This creates the Vite manifest required by Laravel.

## Access

Application:

```text
http://localhost
```

## Database

The MariaDB database is available at:

```text
Host: 127.0.0.1
Port: 3306
Database: life_atlas
Username: sail
Password: password
```

When Laravel connects from inside Docker, it should use:

```env
DB_HOST=mariadb
```

## Useful Docker / Sail Commands

### Start containers

```bash
docker compose up -d
```

### Stop containers

```bash
docker compose down
```

### Restart containers

```bash
docker compose restart
```

### Check container status

```bash
docker compose ps
```

### View logs

```bash
docker compose logs -f
```

### Access the Laravel container

```bash
docker compose exec laravel.test bash
```

## Queue worker and daily backups

The `queue` service starts with `docker compose up -d` and processes jobs from the database queue. Opening Dashboard still decides whether today's backup needs to be queued. The worker does not schedule or trigger daily backups itself, but it will process any jobs already pending when it starts.

The worker uses the same Sail image, MariaDB client, project mount (`.:/var/www/html`), and `sail` network as `laravel.test`. Both containers share `.env` and `storage`, including `storage/app/private/backups`. The worker runs as the Sail user and publishes no ports.

Compose waits for MariaDB's health check before starting the worker. The worker does not depend on the web server and does not run migrations; complete the installation migrations before testing queued jobs. Docker restarts an exited worker unless its container was explicitly stopped.

Run queue workers as `sail`, including any manual diagnostic workers (`docker compose exec --user sail laravel.test php artisan queue:work database --tries=1 --timeout=660`). Running them as root can create a backup directory that the dedicated worker cannot access.

For an existing root-owned backup directory, repair only the directory itself (no recursive changes):

```bash
docker compose exec --user root queue chown sail:sail /var/www/html/storage/app/private/backups
docker compose exec --user root queue chmod 0700 /var/www/html/storage/app/private/backups
```

The backup job creates the directory with private `0700` permissions and checks that it is writable. Dump failures log the exit code and recognized stderr diagnostic phrases; other stderr text is redacted to avoid exposing secrets.

The dedicated worker runs:

```bash
php artisan queue:work database --sleep=3 --tries=1 --timeout=660
```

Keep these timeout settings aligned:

| Setting | Seconds | Purpose |
| --- | --- | --- |
| Backup dump timeout | 600 | Limits the external dump process |
| Worker timeout | 660 | Allows additional time for setup, cleanup, and status updates |
| `DB_QUEUE_RETRY_AFTER` in `.env` | 720 | Keeps a reserved job unavailable longer than the worker timeout |
| Docker `stop_grace_period` | 720 | Gives an active job time to finish during shutdown |

`retry_after` controls when an unfinished queue reservation expires; it is not a backup schedule.

### Activate the worker in an existing installation

Stop any manually launched queue worker first (Ctrl+C in its terminal), so only the dedicated worker remains. With the existing application container running, run:

```bash
docker compose config --quiet
docker compose build
docker compose exec laravel.test php artisan config:clear
docker compose up -d
```

Check the service and its effective queue configuration:

```bash
docker compose ps
docker compose exec queue php artisan config:show queue
docker compose logs -f queue
```

Confirm that the database connection's `retry_after` is `720`. Open Dashboard and watch the backup status and worker logs. An existing pending job may run immediately when the worker starts. If today's backup is already completed, Dashboard should not enqueue another one; test on the next eligible day without deleting backup files or records. Successful backup jobs retain the existing policy of keeping the five latest completed backups.

Ctrl+C stops following logs without stopping the worker. Since `queue:work` keeps Laravel loaded, restart it after changing job code or configuration (clear cached configuration first if needed):

```bash
docker compose restart queue
```

## Useful Artisan Commands

Run an Artisan command:

```bash
docker compose exec laravel.test php artisan
```

Run migrations:

```bash
docker compose exec laravel.test php artisan migrate
```

Run the database seeder:

```bash
docker compose exec laravel.test php artisan db:seed
```

Run tests:

```bash
docker compose exec laravel.test php artisan test
```

Clear Laravel caches:

```bash
docker compose exec laravel.test php artisan optimize:clear
```

## Vite

### Development

For frontend development with Vite:

```bash
docker compose exec laravel.test npm run dev
```

### Local production build

For the local production version:

```bash
docker compose exec laravel.test npm run build
```

## Project Status

🚧 Currently under development.
