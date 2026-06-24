#!/bin/sh
set -e

# Link public storage
php artisan storage:link --force 2>/dev/null || true

# Cache config, routes, and views for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run pending migrations automatically on deploy
php artisan migrate --force

# Hand off to CMD (supervisord or horizon)
exec "$@"
