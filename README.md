# Jason Vertucio Wink Blog

Wink Blog implementation for Jason Vertucio's site.

## Updates

### 2020.07.03

JasonVertucio.com will now require Laravel 7.x, as it is the new requirement for Wink, now on its v1.x branch!

## Dependencies

-   [Composer](https://getcomposer.org) - This dependency manager is required to install all other packages.
-   [Node & NPM](https://nodejs.org) - NodeJS!
-   [Laravel](https://laravel.com) - Laravel 7.x is the base framework for this site
-   [Wink](https://github.com/themsaid/wink) - Wink adds basic blog capabilities to any Laravel blog.

## Development Setup

### Clone repository

```
git clone https://github.com/jvjvjv/winkglog.git
```

### Set up environment

```
cp .env.example .env
```

Update settings to match your environment

### Run migrations

```
php artisan key:generate
php artisan wink:migrate
php artisan migrate
```

### Remember to write down generated user credentials

Mine are admin@mail.com / Du6jcxT3rvGpuOaK, but yours may be different.

### Set up new user

```
php artisan wink:create-user <new-user-email> <new-user-name> [new-user-password]
```

If not entered, you will be prompted for a password.

### Other user functions

```
php artisan wink:change-user-password <user-email> [new-user-password]
```

If not entered, you will be prompted for a password.

## Production Deployment

Deployment to a new server:

1. Clone the repository.
2. Fetch the latest changes.
3. Set up the environment file.
4. Set up database.
5. Download dependencies with `composer install && npm ci`.
6. Run migrations with `php artisan migrate` and `php artisan db:seed`.
7. Set up Apache/Nginx to point to the `public` directory.

## Troubleshooting

### CentOS

In CentOS, SELinux may cause issues with storage.

```
chcon -R -t httpd_sys_rw_content_t storage

```

## Docker

### Prerequisites
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running

### Build and run
```bash
docker compose up --build
```

The application will be available at **http://localhost:8003**.
Vite HMR dev server runs on port **5175** (mapped from internal 5173).

### Database configuration

By default, the app connects to your **host machine's MySQL** via `host.docker.internal`. To use the containerized MariaDB instead, set `DB_HOST=db`:

```bash
DB_HOST=db docker compose up --build
```

Or create a `.env` file:
```env
DB_HOST=db
DB_DATABASE=jasonvertucio
DB_USERNAME=jasonvertucio
DB_PASSWORD=jvsecret
```

### Services

The Docker setup includes:
- **app** — Laravel application with PHP-FPM, Nginx, Vite, and queue worker
- **db** — MariaDB 11 (port 3308)
- **redis** — Redis 7 Alpine (port 6379) for caching, sessions, and queues

### Useful commands
```bash
# Run artisan commands inside the container
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# Generate resume DOCX
docker compose exec app node scripts/generate-resume.js

# Access the container shell
docker compose exec app bash

# Stop all services
docker compose down

# Stop and remove volumes (fresh start)
docker compose down -v
```
