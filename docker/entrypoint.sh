#!/bin/sh

# Railway assigns a dynamic $PORT; bind nginx to it (fallback to 80 locally)
sed -i "s/__PORT__/${PORT:-80}/" /etc/nginx/http.d/default.conf

# Required runtime env vars for production boot.
: "${APP_KEY:?APP_KEY is required}"
: "${DB_CONNECTION:?DB_CONNECTION is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

# Ensure writable storage dirs exist at runtime (realpath() needs them for config:cache)
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache

# Link public storage (non-fatal)
php artisan storage:link --force 2>/dev/null || true

# Publish Livewire JS to public/vendor/livewire/ so nginx serves it as a static
# file, bypassing PHP routing entirely (avoids hash-based route 404 issues).
php artisan vendor:publish --tag=livewire:assets --force 2>/dev/null || true

# Cache config, routes, and views for production performance
php artisan config:cache   || { echo "config:cache failed";   exit 1; }
# route:cache excluded: Livewire 4 registers routes via closures that cannot
# be serialized, causing its asset routes to 404 when the cache is used.
php artisan view:cache     || { echo "view:cache failed";     exit 1; }
php artisan filament:optimize 2>/dev/null || true
php artisan icons:cache    2>/dev/null || true

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

# Hand ownership of writable paths to php-fpm's user (www-data) so it can
# compile views/sessions/cache at runtime. Done last, after the root-run
# artisan cache commands above created their files.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Hand off to CMD (supervisord or horizon)
exec "$@"
