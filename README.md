# Life Atlas

Life Atlas is a personal growth and journaling platform that helps users track different areas of their lives through notes, metrics, and visual progress.

## Tech Stack

* Laravel 12
* Livewire
* MariaDB
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

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Create the environment file

```bash
cp .env.example .env
```

### 4. Start Docker

```bash
docker compose up --build -d
```

### 5. Generate the application key

```bash
docker exec lifeatlas-laravel.test-1 php artisan key:generate
```

### 6. Run migrations and seed the database

```bash
docker exec lifeatlas-laravel.test-1 php artisan migrate --seed
```

This runs the migrations and the `DatabaseSeeder`, which seeds the initial life areas.

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

## Useful Docker Commands

### Start containers

```bash
docker compose up -d
```

### Stop containers

```bash
docker compose down
```

### View logs

```bash
docker compose logs -f
```

## Useful Artisan Commands

Run an Artisan command:

```bash
docker exec lifeatlas-laravel.test-1 php artisan
```

Run migrations:

```bash
docker exec lifeatlas-laravel.test-1 php artisan migrate
```

Run the database seeder:

```bash
docker exec lifeatlas-laravel.test-1 php artisan db:seed
```

Run tests:

```bash
docker exec lifeatlas-laravel.test-1 php artisan test
```

## Project Status

🚧 Currently under development.
