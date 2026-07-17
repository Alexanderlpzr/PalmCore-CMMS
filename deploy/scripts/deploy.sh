#!/usr/bin/env bash
#
# PalmCore CMMS - Production deploy script
#
# Invoked by .github/workflows/deploy.yml over SSH on every push to master.
# Validates the compose config, updates the code, rebuilds/restarts the
# stack, runs migrations, optimizes the app and verifies it is healthy.
#
# There is intentionally NO automatic rollback: Laravel migrations are not
# safely reversible by resetting the git tree (a migration already applied
# against the database would be out of sync with reverted code). On any
# failure this script stops immediately, prints diagnostics, and exits 1
# so a human can decide the fix (e.g. a down migration or a fix-forward).
#
# Usage: bash deploy/scripts/deploy.sh
# Must be run from the repository root (/opt/palmcore).

set -euo pipefail

# ─── Configuration ────────────────────────────────────────────────────────────
APP_DIR="/opt/palmcore"
COMPOSE_FILE="deploy/docker-compose.yml"
ENV_FILE=".env.production"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://fronda.app/up}"
HEALTHCHECK_INITIAL_WAIT=5
HEALTHCHECK_RETRIES=10
HEALTHCHECK_DELAY=3
CONTAINER_WAIT_TIMEOUT=90
CONTAINER_WAIT_INTERVAL=2

# Containers to wait for after `up -d --build`, matched to container_name in
# deploy/docker-compose.yml.
WAIT_CONTAINERS=("palmcore-app" "palmcore-postgres" "palmcore-redis")

compose() {
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
}

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1"
}

# Prints diagnostics (compose state + recent logs) and exits 1. No rollback.
fail() {
    local reason="$1"

    log "ERROR: ${reason}"

    log "---- docker compose ps ----"
    compose ps || true

    log "---- docker compose logs app --tail=100 ----"
    compose logs app --tail=100 || true

    log "---- docker compose logs caddy --tail=100 ----"
    compose logs caddy --tail=100 || true

    log "Deploy FAILED. No automatic rollback was performed. Manual review required."
    exit 1
}

# ─── Pre-flight validations ───────────────────────────────────────────────────
log "Starting deploy"

[ -d "$APP_DIR" ] || { log "ERROR: Directory ${APP_DIR} does not exist"; exit 1; }
cd "$APP_DIR"

[ -d ".git" ] || { log "ERROR: ${APP_DIR} is not a git repository"; exit 1; }
[ -f "$COMPOSE_FILE" ] || { log "ERROR: ${COMPOSE_FILE} not found"; exit 1; }
[ -f "$ENV_FILE" ] || { log "ERROR: ${ENV_FILE} not found in ${APP_DIR}"; exit 1; }

command -v docker >/dev/null 2>&1 || { log "ERROR: docker is not installed"; exit 1; }
docker compose version >/dev/null 2>&1 || { log "ERROR: docker compose (v2) plugin is not available"; exit 1; }

CURRENT_COMMIT="$(git rev-parse HEAD)"
log "Current commit: ${CURRENT_COMMIT}"

# ─── Validate compose configuration ────────────────────────────────────────────
log "Validating docker compose configuration"
if ! compose config > /dev/null; then
    log "ERROR: docker compose config validation failed"
    exit 1
fi

# ─── Update source ─────────────────────────────────────────────────────────────
log "Fetching latest changes from origin"
git fetch origin

log "Resetting working tree to origin/master"
git reset --hard origin/master

NEW_COMMIT="$(git rev-parse HEAD)"
log "New commit: ${NEW_COMMIT}"

# ─── Build and start containers ────────────────────────────────────────────────
log "Pulling upstream images (best effort)"
compose pull || true

log "Building and starting containers"
if ! compose up -d --build; then
    fail "docker compose up --build failed"
fi

# ─── Wait for containers to be healthy/running ─────────────────────────────────
log "Waiting for containers to become healthy/running"

for container in "${WAIT_CONTAINERS[@]}"; do
    waited=0

    while true; do
        status="$(docker inspect -f '{{.State.Status}}' "$container" 2>/dev/null || echo "missing")"
        health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$container" 2>/dev/null || echo "")"

        if [ "$status" = "missing" ]; then
            fail "Container ${container} was not found"
        fi

        if [ "$health" = "unhealthy" ]; then
            fail "Container ${container} reported unhealthy"
        fi

        if [ "$status" = "running" ] && { [ -z "$health" ] || [ "$health" = "healthy" ]; }; then
            log "Container ${container} is ${status}${health:+ (${health})}"
            break
        fi

        waited=$((waited + CONTAINER_WAIT_INTERVAL))
        if [ "$waited" -ge "$CONTAINER_WAIT_TIMEOUT" ]; then
            fail "Timed out waiting for ${container} to become healthy/running"
        fi

        sleep "$CONTAINER_WAIT_INTERVAL"
    done
done

# ─── Database migrations ───────────────────────────────────────────────────────
log "Running database migrations"
if ! compose exec -T app php artisan migrate --force; then
    fail "php artisan migrate --force failed"
fi

# ─── Application optimization ──────────────────────────────────────────────────
log "Optimizing application (config/route/view cache)"
if ! compose exec -T app php artisan optimize; then
    fail "php artisan optimize failed"
fi

# ─── Reload Caddy ───────────────────────────────────────────────────────────────
# El Caddyfile se monta como archivo: `docker compose up` no recrea el contenedor
# por un cambio del archivo, así que Caddy seguiría con el config viejo en memoria.
# `caddy reload` valida y aplica el nuevo config sin downtime; si fuera inválido,
# Caddy conserva el anterior y esto solo deja una advertencia, sin tumbar el deploy.
log "Reloading Caddy configuration"
if ! compose exec -T caddy caddy reload --config /etc/caddy/Caddyfile; then
    log "WARNING: caddy reload failed — Caddy keeps its previous config"
fi

# ─── Cleanup ────────────────────────────────────────────────────────────────────
log "Pruning dangling images"
docker image prune -f

# ─── Healthcheck ────────────────────────────────────────────────────────────────
log "Waiting ${HEALTHCHECK_INITIAL_WAIT}s before healthcheck"
sleep "$HEALTHCHECK_INITIAL_WAIT"

log "Running healthcheck against ${HEALTHCHECK_URL}"

http_code=""
for attempt in $(seq 1 "$HEALTHCHECK_RETRIES"); do
    http_code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$HEALTHCHECK_URL" || echo "000")"

    if [ "$http_code" = "200" ]; then
        log "Healthcheck OK (HTTP 200)"
        break
    fi

    log "Healthcheck attempt ${attempt}/${HEALTHCHECK_RETRIES} returned HTTP ${http_code}, retrying in ${HEALTHCHECK_DELAY}s"
    sleep "$HEALTHCHECK_DELAY"
done

if [ "$http_code" != "200" ]; then
    fail "Healthcheck against ${HEALTHCHECK_URL} did not return HTTP 200 (last: ${http_code})"
fi

log "Deploy completed successfully (${CURRENT_COMMIT} -> ${NEW_COMMIT})"
