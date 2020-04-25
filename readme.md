# Jason Vertucio Wink Blog

Wink Blog implementation for Jason Vertucio's site.

## Dependencies

* [Composer](https://getcomposer.org) - This dependency manager is required to install all other packages.
* [Laravel](https://laravel.com) - Laravel 6.x is the base framework for this site
* [Wink](https://github.com/writingink/wink) - Wink, in 0.1.0 at the time of writing, adds basic blog capabilities to any Laravel blog.

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
