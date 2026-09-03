# Jason Vertucio's Personal Website & Blog

Jason Vertucio's personal website, blog, and portfolio built with **Laravel 13** and **Canvas**. This project serves as a modern web application featuring a CMS-powered blog, resume/portfolio section, and AI conversation tracking. As the site is designed to showcase Jason's work it may contain some competing frameworks especially in the history. I used to use Vue and Tailwind for the frontend, but I have been slowly migrating to Inertia.js with MUI Material and React. The public site is still Blade components where the auth tier leverages Inertia and React for a modern SPA-like experience within the Laravel ecosystem.

The blog is powered by Canvas, which provides a user-friendly interface for managing posts, categories, and tags. The resume/portfolio section is built with custom Laravel models and views, allowing for easy updates and maintenance. Additionally, the site includes AI conversation tracking to monitor token usage and costs for any AI interactions.

## Features

- **Laravel 13** - Latest PHP framework features
- **Canvas 6.x** - Blog and content management system (successor to Wink)
- **Inertia.js 2.x** - SPA-like experience with server-side rendering
- **React 19** - Modern frontend library
- **Vite** - Fast build tooling and hot module replacement
- **Docker support** - Consistent development environment
- **Role-based access control** - bspdx/keystone package
- **AI Conversation Tracking** - Token usage and cost tracking for AI interactions

## Requirements

- [Composer](https://getcomposer.org) - PHP dependency manager
- [Node.js & NPM](https://nodejs.org) - JavaScript runtime (v22+ recommended)
- [PHP 8.4+](https://php.net) - Required by Laravel 13
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) - Optional but recommended

## Quick Start with Docker (Recommended)

### Prerequisites

- Docker Desktop installed and running
- Git cloned locally

### Setup

```bash
# Clone the repository
git clone https://github.com/jvjvjv/jasonvertucio.com.git
cd jasonvertucio.com

# Build and run containers
docker compose up --build
```

The application will be available at **http://localhost:8003**.

### Database Configuration Options

By default, the app connects to your **host machine's MySQL** via `host.docker.internal`. This is useful for using an existing database. The containerized MariaDB service is currently disabled in `docker-compose.yml`.

To use the containerized MariaDB instead, uncomment the `db` service and its related volume and dependency in `docker-compose.yml`, then update the app's `DB_HOST` environment value to `db`.

```bash
# Rebuild and start after updating docker-compose.yml
docker compose up --build
```

Or create a `.env` file with these settings:

```env
DB_HOST=db
DB_DATABASE=jasonvertucio
DB_USERNAME=jasonvertucio
DB_PASSWORD=jvsecret
```

### Initial Setup (First Run)

After starting the containers for the first time, run migrations and seed the database:

```bash
# Run database migrations
docker compose exec app php artisan migrate

# Seed initial data
docker compose exec app php artisan db:seed

# Create a CMS admin user (interactive mode recommended)
docker compose exec app php artisan user:make --admin
```

### Available Services

| Service | Host Port | Container Port | Description                                   |
| ------- | --------- | -------------- | --------------------------------------------- |
| app     | 8003      | 80             | Laravel application with PHP-FPM, Nginx, Vite |
| redis   | 7000      | 6379           | Redis 7 Alpine for caching and queues         |

### Useful Docker Commands

```bash
# Run artisan commands inside the container
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan cache:clear

# Access the application shell
docker compose exec app bash

# View logs
docker compose logs -f app

# Stop all services
docker compose down

# Restart with fresh database (removes volumes)
docker compose down -v && docker compose up --build

# Sync active AI conversation usage data
docker compose exec app php artisan ai:sync-conversation-usage --minutes=10
```

## Local Development Setup

If you prefer to run the application locally without Docker:

### Installation

1. **Clone the repository**

    ```bash
    git clone https://github.com/jvjvjv/jasonvertucio.com.git
    cd jasonvertucio.com
    ```

2. **Install PHP dependencies**

    ```bash
    composer install
    ```

3. **Install JavaScript dependencies**

    ```bash
    npm install
    ```

4. **Set up environment**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

5. **Configure database and run migrations**

    ```bash
    # Update .env with your database credentials
    php artisan migrate
    php artisan db:seed
    ```

6. **Create a CMS admin user**

    ```bash
    # Interactive mode (recommended)
    php artisan user:make --admin

    # Or use command-line arguments
    php artisan user:create admin@example.com "Admin User" password123 --role=admin
    ```

7. **Start the development environment**

    ```bash
    npm run dev
    ```

    This starts Vite, the local HTTPS proxy, and Laravel. The Laravel server listens on port 8001, the proxy on port 8000, and Vite on port 5173.

## Available Artisan Commands

### User Management Commands

| Command            | Description                       | Example                                                        |
| ------------------ | --------------------------------- | -------------------------------------------------------------- |
| `user:make`        | Interactively create a new user   | `php artisan user:make --admin`                                |
| `user:create`      | Create user via CLI arguments     | `php artisan user:create email name [password] [--role=admin]` |
| `user:list`        | List all users with roles         | `php artisan user:list`                                        |
| `user:list-roles`  | Show system roles or user's roles | `php artisan user:list-roles <email>`                          |
| `user:info`        | Display detailed user information | `php artisan user:info <email>`                                |
| `user:password`    | Change a user's password          | `php artisan user:password <email> [new-password]`             |
| `user:add-role`    | Assign a role to a user           | `php artisan user:add-role <email> <role>`                     |
| `user:remove-role` | Remove a role from a user         | `php artisan user:remove-role <email> <role>`                  |
| `user:sync-roles`  | Replace all roles on a user       | `php artisan user:sync-roles <email> admin editor`             |
| `user:delete`      | Delete a user account             | `php artisan user:delete <email> --force`                      |

### AI Conversation Commands

| Command                      | Description                     | Example                                               |
| ---------------------------- | ------------------------------- | ----------------------------------------------------- |
| `ai:sync-conversation-usage` | Sync token usage and costs      | `php artisan ai:sync-conversation-usage --minutes=10` |
| `paper:harvest`              | Harvest paper editions from API | `php artisan paper:harvest --limit=5`                 |

### Resume Commands

| Command                | Description                        | Example                                    |
| ---------------------- | ---------------------------------- | ------------------------------------------ |
| `resume:migrate-to-db` | Migrate resume content to database | `php artisan resume:migrate-to-db --force` |

### Database Commands

```bash
# Run migrations
php artisan migrate

# Seed the database with initial data
php artisan db:seed

# View all available commands
php artisan list
```

## Project Structure

```
├── app/                    # Application logic
│   ├── Actions/           # Action classes for complex operations
│   ├── Console/Commands/  # Custom artisan commands
│   ├── Contracts/         # Interface definitions
│   ├── Enums/             # Enum value objects
│   ├── Models/            # Eloquent models (User, Paper, AiConversation)
│   └── Services/          # Business logic services
├── bootstrap/              # Framework bootstrapping files
├── config/                 # Configuration files
├── database/               # Migrations and seeders
├── docker/                 # Docker configuration files
├── docs/                   # Additional documentation
├── resources/              # Views, assets, templates
│   ├── js/                # React components (Inertia)
│   └── views/             # Blade templates
├── routes/                 # Application route definitions
├── storage/                # Logs, cache, uploaded files
│   ├── app/               # Publicly accessible files
│   ├── framework/         # Cache, sessions, views
│   └── logs/              # Application logs
└── tests/                  # Unit and feature tests
```

## Technologies Used

| Technology                                          | Version | Purpose                                        |
| --------------------------------------------------- | ------- | ---------------------------------------------- |
| [Laravel](https://laravel.com)                      | 13.x    | PHP Framework                                  |
| [Canvas](https://trycanvas.app/)                    | 6.x     | Blog/CMS system                                |
| [Inertia.js](https://inertiajs.com)                 | 2.x     | Server-side SPA rendering                      |
| [React](https://react.dev)                          | 19.x    | Frontend library                               |
| [Vite](https://vitejs.dev)                          | 7.x     | Build tooling and asset bundling               |
| [MariaDB](https://mariadb.org)                      | 11.x    | Optional database (disabled in Docker Compose) |
| [MySQL](https://mysql.com)                          | 8.0+    | Database (host connection)                     |
| [Redis](https://redis.io)                           | 7.x     | Caching and queues                             |
| [bspdx/keystone](https://github.com/bspdx/keystone) | 0.10.x  | Role-based access control                      |

## Development Notes

### Port Configuration

- **Application (Docker)**: 8003 (Nginx)
- **Application (Local)**: 8001 (Laravel), 8000 (HTTPS proxy)
- **Vite Dev Server (Docker)**: Host 5175 → Container 5173
- **Vite Dev Server (Local)**: 5173
- **Redis (Docker)**: Host 7000 → Container 6379

### Environment Variables

Key environment variables to configure:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=host.docker.internal  # or 'db' for internal MariaDB
DB_PORT=3306
DB_DATABASE=jasonvertucio
DB_USERNAME=root
DB_PASSWORD=

# Redis (for caching and queues)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Paper.li integration
PAPER_ID=your_paper_id

# Twilio (for SMS features if enabled)
TWILIO_ACCOUNT_SID=your_sid
TWILIO_AUTH_TOKEN=your_token
TWILIO_FROM_NUMBER=+1234567890
```

### Troubleshooting

#### Storage Permissions (Linux/CentOS/RHEL)

If you encounter permission issues with the storage directory:

```bash
# Set appropriate SELinux context
chcon -R -t httpd_sys_rw_content_t storage

# Or reset permissions
chmod -R 775 storage
chown -R www-data:www-data storage
```

#### Docker Issues

If experiencing container connection issues:

```bash
# Restart containers
docker compose restart

# Check container status
docker compose ps

# View error logs
docker compose logs app

# Rebuild with fresh state
docker compose down -v && docker compose build --no-cache && docker compose up --build
```

#### Database Connection Issues

If unable to connect to host MySQL from Docker:

```bash
# Check if host MySQL is running
mysql -h 127.0.0.1 -u root -p

# Use internal MariaDB instead after enabling the db service in docker-compose.yml
docker compose up --build
```

## License & Credits

This project is the personal website of **Jason Vertucio**. Built with Laravel, Canvas, and modern web technologies.

**Copyright © 2026 Jason Vertucio. All rights reserved.**

---

_Last updated: September 2026_
