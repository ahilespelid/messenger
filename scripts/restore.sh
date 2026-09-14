#!/usr/bin/env bash
#
# Restores the database and uploaded files from a backup produced by
# scripts/backup.sh. OVERWRITES current data — asks for confirmation.
#
# Usage: ./scripts/restore.sh backups/daily/2026-09-15_030001

set -euo pipefail
cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1 # avoid Git-Bash mangling /app/... container paths on Windows

DIR="${1:-}"
if [[ -z "$DIR" || ! -f "${DIR}/db.sql.gz" ]]; then
    echo "Usage: $0 <path-to-backup-dir>" >&2
    echo "Example: $0 backups/daily/2026-09-15_030001" >&2
    echo "List available backups with: ls backups/daily backups/weekly backups/monthly" >&2
    exit 1
fi

set -a
source .env
set +a

echo "This will OVERWRITE the current database and uploaded files with the"
echo "contents of: ${DIR}"
read -rp "Type 'yes' to continue: " CONFIRM
[[ "$CONFIRM" == "yes" ]] || { echo "Aborted."; exit 1; }

echo "==> Restoring database"
gunzip < "${DIR}/db.sql.gz" | docker compose exec -T db mysql \
    -u root -p"${DB_ROOT_PASSWORD}" "${DB_DATABASE:-messenger}"

if [[ -f "${DIR}/uploads.tar.gz" ]]; then
    echo "==> Restoring uploaded files"
    docker compose exec -T app sh -c \
        'rm -rf /app/storage/app/private && mkdir -p /app/storage/app/private && tar xzf - -C /app/storage/app/private' \
        < "${DIR}/uploads.tar.gz"
fi

echo "==> Restored from ${DIR}"
