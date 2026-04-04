#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SEED_MODE="${SEED_MODE:-auto}" # auto|always|never

usage() {
  cat <<'EOF'
Usage: ./deploy-local.sh [options]

Options:
  --php-bin <path>        PHP binary path (default: php)
  --composer-bin <path>   Composer binary path (default: composer)
  --seed <mode>           Seed mode: auto | always | never (default: auto)
  -h, --help              Show this help

Environment variable overrides:
  PHP_BIN, COMPOSER_BIN, SEED_MODE
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --php-bin)
      PHP_BIN="$2"
      shift 2
      ;;
    --composer-bin)
      COMPOSER_BIN="$2"
      shift 2
      ;;
    --seed)
      SEED_MODE="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ ! -x "$PHP_BIN" && "$PHP_BIN" != "php" ]]; then
  echo "PHP binary not executable: $PHP_BIN" >&2
  exit 1
fi

if [[ "$SEED_MODE" != "auto" && "$SEED_MODE" != "always" && "$SEED_MODE" != "never" ]]; then
  echo "Invalid --seed mode: $SEED_MODE (expected auto|always|never)" >&2
  exit 1
fi

run() {
  echo ""
  echo "==> $*"
  "$@"
}

read_env_value() {
  local key="$1"
  local default="$2"
  local file="$ROOT_DIR/.env"

  if [[ ! -f "$file" ]]; then
    echo "$default"
    return
  fi

  local line
  line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"

  if [[ -z "$line" ]]; then
    echo "$default"
    return
  fi

  local value="${line#*=}"
  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"
  echo "$value"
}

admin_local_development="$(read_env_value "ADMIN_LOCAL_DEVELOPMENT" "true")"
admin_use_mock_data="$(read_env_value "ADMIN_USE_MOCK_DATA" "true")"
sqlite_path="$(read_env_value "DB_SQLITE_PATH" "database/database.sqlite")"

if [[ "${admin_local_development}" != "true" ]]; then
  echo "Warning: ADMIN_LOCAL_DEVELOPMENT is not true in .env. Local script will continue." >&2
fi

if [[ "$sqlite_path" != /* ]]; then
  sqlite_path="$ROOT_DIR/$sqlite_path"
fi

if [[ "${admin_local_development}" == "true" ]]; then
  run "$COMPOSER_BIN" install --no-interaction
else
  run "$PHP_BIN" "$COMPOSER_BIN" install --no-interaction
fi

run "$PHP_BIN" artisan optimize:clear --no-interaction

run mkdir -p "$(dirname "$sqlite_path")"
run touch "$sqlite_path"

run "$PHP_BIN" artisan migrate:fresh --force --no-interaction

run_seed="no"
if [[ "$SEED_MODE" == "always" ]]; then
  run_seed="yes"
elif [[ "$SEED_MODE" == "auto" && "${admin_use_mock_data}" == "true" ]]; then
  run_seed="yes"
fi

if [[ "$run_seed" == "yes" ]]; then
  run "$PHP_BIN" artisan db:seed --force --no-interaction
else
  echo ""
  echo "==> Skipping seeding (mode: $SEED_MODE)"
fi

echo ""
echo "Local deploy steps completed."
