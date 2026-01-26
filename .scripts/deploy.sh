#!/bin/bash
set -e

echo "Deployment started ..."

# Enter maintenance mode or return true if already in maintenance mode
(php artisan down) || true

# Pull the latest version of the app from main branch
git fetch origin
git reset --hard origin/main



# Install composer dependencies
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Clear old cache
php artisan clear-compiled

# Recreate cache and optimize
php artisan optimize

# Compile npm assets
# npm run prod

# Run database migrations
# php artisan migrate --force

# Exit maintenance mode
php artisan up

echo "Deployment finished!"
