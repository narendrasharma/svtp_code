#!/usr/bin/env bash
#
# Manual backup of the active development database.
#
# Usage:  bash scripts/backup-db.sh
#
# - Reads connection details from Laravel config (never hardcodes credentials).
# - Never prints the password (it is prompted interactively, or read from
#   the DB_PASSWORD environment variable for scripted use).
# - Writes a timestamped dump to storage/backups/ (ignored by git).
# - This script is read-only apart from creating the dump file: it never
#   modifies or drops anything.
#
set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v mysqldump >/dev/null 2>&1; then
    echo "ERROR: mysqldump is not installed." >&2
    exit 1
fi

eval "$(php artisan db:check --shell-export)"

if [ "${DB_CHECK_CONNECTION}" != "mysql" ] && [ "${DB_CHECK_CONNECTION}" != "mariadb" ]; then
    echo "ERROR: backup script supports mysql/mariadb only (current: ${DB_CHECK_CONNECTION})." >&2
    exit 1
fi

if [ -z "${DB_PASSWORD:-}" ]; then
    read -rsp "Password for MySQL user '${DB_CHECK_USER}': " DB_PASSWORD
    echo ""
fi
export MYSQL_PWD="$DB_PASSWORD"

mkdir -p storage/backups
FILE="storage/backups/${DB_CHECK_NAME}_$(date +%Y%m%d_%H%M%S).sql"

mysqldump -h "${DB_CHECK_HOST}" -P "${DB_CHECK_PORT:-3306}" -u "${DB_CHECK_USER}" "${DB_CHECK_NAME}" > "${FILE}"

unset MYSQL_PWD
unset DB_PASSWORD

echo "Backup written to ${FILE}"
