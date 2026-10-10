#!/usr/bin/env bash
set -Eeuo pipefail

# Spuštěno mimo shell (poklepáním / z menu)? Po skončení nechat okno otevřené
# s běžným promptem, jako by se to psalo ručně. Z terminálu se nic nemění.
keep_window_open() {
  local status=$?
  if [[ -t 0 ]] && ! ps -o comm= -p "$PPID" | grep -qE '^-?(ba|z|fi|da|k|tc)?sh$'; then
    exec "${SHELL:-bash}" -i
  fi
  exit "$status"
}
trap keep_window_open EXIT

CONFIG_FILE="${1:-deploy/deploy.env}"

log() {
  printf '[deploy] %s\n' "$*"
}

fail() {
  printf '[deploy] ERROR: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

if [[ ! -f "$CONFIG_FILE" ]]; then
  fail "Config file not found: $CONFIG_FILE. Copy deploy/deploy.env.example to deploy/deploy.env first."
fi

# shellcheck disable=SC1090
source "$CONFIG_FILE"

: "${SSH_TARGET:?SSH_TARGET is required in deploy config}"
: "${REMOTE_PATH:?REMOTE_PATH is required in deploy config}"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
APP_CONTAINER="${APP_CONTAINER:-kremacia_app}"
PHP_FPM_PORT="${PHP_FPM_PORT:-9001}"
RELOAD_NGINX="${RELOAD_NGINX:-false}"
NGINX_RELOAD_COMMAND="${NGINX_RELOAD_COMMAND:-sudo systemctl reload nginx}"
EXTRA_RSYNC_EXCLUDES="${EXTRA_RSYNC_EXCLUDES:-}"

require_command ssh
require_command rsync

[[ -f "$COMPOSE_FILE" ]] || fail "Compose file not found: $COMPOSE_FILE"
[[ -f "artisan" ]] || fail "Run deploy.sh from the Laravel project root"

log "Deploy target: $SSH_TARGET:$REMOTE_PATH"

log "Creating remote directory"
ssh "$SSH_TARGET" "mkdir -p '$REMOTE_PATH'"

RSYNC_EXCLUDES=(
  "--exclude=.git/"
  "--exclude=.idea/"
  "--exclude=.env"
  "--exclude=vendor/"
  "--exclude=node_modules/"
  "--exclude=storage/"
  "--exclude=bootstrap/cache/"
  "--exclude=deploy/deploy.env"
)

if [[ -n "$EXTRA_RSYNC_EXCLUDES" ]]; then
  # shellcheck disable=SC2206
  EXTRA_EXCLUDES_ARRAY=($EXTRA_RSYNC_EXCLUDES)
  for pattern in "${EXTRA_EXCLUDES_ARRAY[@]}"; do
    RSYNC_EXCLUDES+=("--exclude=$pattern")
  done
fi

log "Uploading files with rsync"
rsync -az --delete --omit-dir-times --no-perms \
  "${RSYNC_EXCLUDES[@]}" \
  ./ "$SSH_TARGET:$REMOTE_PATH/"

log "Running remote deploy commands"
ssh "$SSH_TARGET" \
  "REMOTE_PATH='$REMOTE_PATH' COMPOSE_FILE='$COMPOSE_FILE' APP_CONTAINER='$APP_CONTAINER' PHP_FPM_PORT='$PHP_FPM_PORT' RELOAD_NGINX='$RELOAD_NGINX' NGINX_RELOAD_COMMAND='$NGINX_RELOAD_COMMAND' bash -s" <<'REMOTE_SCRIPT'
set -Eeuo pipefail

cd "$REMOTE_PATH"

if [[ ! -f .env ]]; then
  printf '[deploy] ERROR: Remote .env is missing. Create production .env at %s/.env before deploy.\n' "$REMOTE_PATH" >&2
  exit 1
fi

mkdir -p vendor storage/logs storage/framework/cache storage/framework/sessions storage/framework/views storage/app/private bootstrap/cache

docker compose -f "$COMPOSE_FILE" build
docker compose -f "$COMPOSE_FILE" up -d

docker exec -u root "$APP_CONTAINER" composer install --no-dev --optimize-autoloader --no-interaction
docker exec -u root "$APP_CONTAINER" chown -R www-data:www-data /var/www/vendor /var/www/storage /var/www/bootstrap/cache
# Při prvním nasazení vygenerovat APP_KEY. Pozor: pozdější změna klíče
# znehodnotí uložené přístupové odkazy (jsou jím zašifrované) – pak vydat nové.
if ! grep -q '^APP_KEY=base64:' .env; then
  docker exec "$APP_CONTAINER" php artisan key:generate --force
fi

docker exec "$APP_CONTAINER" php artisan migrate --force
docker exec "$APP_CONTAINER" php artisan config:cache
docker exec "$APP_CONTAINER" php artisan route:cache
docker exec "$APP_CONTAINER" php artisan view:cache

if [[ "$RELOAD_NGINX" == "true" ]]; then
  eval "$NGINX_RELOAD_COMMAND"
fi
REMOTE_SCRIPT

log "Deploy complete"
