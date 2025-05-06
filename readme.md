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
