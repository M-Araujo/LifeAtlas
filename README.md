# Life Atlas

Life Atlas is a personal growth and journaling platform that helps users track different areas of their lives through notes, metrics and visual progress.

## Tech Stack

- Laravel 12
- Livewire
- MariaDB
- Docker
- Docker Compose

## Requirements

- Docker Desktop
- Git

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/<username>/life-atlas.git
cd life-atlas
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install JavaScript dependencies

```bash
npm install
```

### 4. Create the environment file

```bash
cp .env.example .env
```

### 5. Generate the application key

```bash
php artisan key:generate
```

### 6. Start Docker

```bash
docker compose up --build -d
```

### 7. Run the database migrations

```bash
docker compose exec laravel.test php artisan migrate
```

### 8. Install frontend assets

```bash
npm run dev
```

## Access

Application

```
http://localhost
```

## Database

Host

```
127.0.0.1
```

Port

```
3306
```

Database

```
life_atlas
```

Username

```
sail
```

Password

```
password
```

## Useful Commands

Start containers

```bash
docker compose up -d
```

Stop containers

```bash
docker compose down
```

View logs

```bash
docker compose logs -f
```

Run Artisan

```bash
docker compose exec laravel.test php artisan
```

Run migrations

```bash
docker compose exec laravel.test php artisan migrate
```

Run tests

```bash
docker compose exec laravel.test php artisan test
```

## Project Status

🚧 Currently under development.
