#!/usr/bin/env bash
#
# Dumps the database and uploaded files (voice messages, images, documents),
# then rotates old backups. Safe to run manually; intended to also run daily
# via cron (deploy.sh installs that automatically).
#
# Backups live in ./backups on the HOST filesystem — not in a Docker named
# volume — so `docker compose down -v` (a full reset) never touches them.
#
# Rotation keeps:
#   - every daily backup for 14 days
#   - the Sunday backup for the last 8 weeks
#   - the 1st-of-month backup for the last 12 months
#
# Usage: ./scripts/backup.sh

set -euo pipefail
cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1 # avoid Git-Bash mangling /app/... container paths on Windows

set -a
source .env
set +a

NOW="$(date +%Y-%m-%d_%H%M%S)"
DEST="backups/daily/${NOW}"
mkdir -p "$DEST"

echo "==> Dumping database"
docker compose exec -T db mysqldump \
    -u root -p"${DB_ROOT_PASSWORD}" \
    --single-transaction --quick --routines --triggers \
    "${DB_DATABASE:-messenger}" | gzip > "${DEST}/db.sql.gz"

echo "==> Archiving uploaded files"
docker compose exec -T app tar czf - -C /app/storage/app/private . > "${DEST}/uploads.tar.gz"

echo "==> Saved to ${DEST}"

# --- promote into weekly/monthly buckets ---
DOW="$(date +%u)" # 1=Monday .. 7=Sunday
DOM="$(date +%d)"

if [[ "$DOW" == "7" ]]; then
    mkdir -p backups/weekly
    cp -r "$DEST" "backups/weekly/${NOW}"
fi

if [[ "$DOM" == "01" ]]; then
    mkdir -p backups/monthly
    cp -r "$DEST" "backups/monthly/${NOW}"
fi

# --- prune each bucket, oldest first ---
prune() {
    local dir="$1" keep="$2"
    [[ -d "$dir" ]] || return 0
    # shellcheck disable=SC2012
    ls -1d "${dir}"/*/ 2>/dev/null | sort | head -n "-${keep}" | xargs -r rm -rf
}

prune backups/daily 14
prune backups/weekly 8
prune backups/monthly 12

echo "==> Rotation done (keeping 14 daily / 8 weekly / 12 monthly)"
