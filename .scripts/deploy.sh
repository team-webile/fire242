#!/bin/bash
set -e

echo "Deployment started ..."

php artisan down

git fetch origin
git reset --hard origin/main

composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan up

echo "Deployment finished!"
