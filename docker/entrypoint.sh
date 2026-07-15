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

# Register any permission the code now checks for but the database has never
# heard of. Sin esto, un permiso nuevo en el código (p. ej. `contractors.view`
# al agregar Contratistas) tumba el panel admin ENTERO en el primer despliegue:
# Filament construye el menú lateral revisando canViewAny() de cada recurso en
# cada carga de página, y Spatie lanza una excepción si el permiso ni siquiera
# existe como fila -- sin importar si el usuario lo tiene o no. Pasó de verdad
# el 2026-07-14 y tuvo el panel admin caído hasta que alguien lo corrió a mano.
#
# firstOrCreate() por nombre+guard hace esto puramente aditivo: nunca duplica,
# nunca borra, seguro de correr en cada arranque del contenedor.
#
# Lo que esto NO hace: asignar el permiso nuevo a los roles de un tenant que
# ya existía antes de que el permiso se agregara al código. Eso lo hace
# TenantRolesSeeder::run($tenant), que reemplaza la lista completa de permisos
# de cada rol -- por eso queda como paso manual y revisado, no automático:
# si algún día alguien personaliza un rol a mano desde el panel, correr esto
# sin pensar se lo llevaría por delante en el próximo despliegue.
# Falla el despliegue si esto falla: dejarlo pasar en silencio sería desplegar
# el mismo bug que este paso existe para evitar.
php artisan db:seed --class="Database\Seeders\PermissionSeeder" --force \
    || { echo "PermissionSeeder failed"; exit 1; }

# Hand ownership of writable paths to php-fpm's user (www-data) so it can
# compile views/sessions/cache at runtime. Done last, after the root-run
# artisan cache commands above created their files.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Hand off to CMD (supervisord or horizon)
exec "$@"
