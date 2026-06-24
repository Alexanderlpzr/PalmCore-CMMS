#!/bin/sh

# Ensure writable storage dirs exist at runtime (realpath() needs them for config:cache)
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Link public storage (non-fatal)
php artisan storage:link --force 2>/dev/null || true

# Cache config, routes, and views for production performance
php artisan config:cache || { echo "config:cache failed"; exit 1; }
php artisan route:cache  || { echo "route:cache failed";  exit 1; }
php artisan view:cache   || { echo "view:cache failed";   exit 1; }

# Wait for DB and run migrations (retry up to 5 times)
retries=5
until php artisan migrate --force; do
    retries=$((retries - 1))
    if [ "$retries" -eq 0 ]; then
        echo "migrate failed after retries"
        exit 1
    fi
    echo "DB not ready, retrying in 5s... ($retries left)"
    sleep 5
done

# Hand off to CMD (supervisord or horizon)
exec "$@"
