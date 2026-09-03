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
