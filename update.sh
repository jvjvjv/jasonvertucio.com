#!/bin/bash

# Retrieve latest
git pull

# Update Laravel dependencies
composer install
php artisan migrate --force
php artisan db:seed --force

# Update front-end dependencies
npm ci
npm run prod

