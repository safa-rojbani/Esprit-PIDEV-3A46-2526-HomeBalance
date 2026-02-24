#!/bin/sh
set -e

# Ensure var/ directory exists and is writable by www-data
mkdir -p /var/www/html/var /var/www/html/public/uploads
chown -R www-data:www-data /var/www/html/var /var/www/html/public/uploads

# Run the original PHP-FPM entrypoint
exec docker-php-entrypoint "$@"
