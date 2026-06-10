#!/usr/bin/env bash
# infra/backup/mysqldump-to-s3.sh
#
# Nightly mysqldump → /backups/codex/ → S3 STANDARD_IA bucket with
# 30-day retention. Run via cron at 03:00.
#
# Restore from this via infra/RECOVERY.md → "Restore from S3 backup".

set -euo pipefail

# Source from /var/www/codex/shared/.env (file mode 0640 root:www-data).
ENV=/var/www/codex/shared/.env
if [ ! -r "$ENV" ]; then
    echo "✗ $ENV not readable" >&2
    exit 1
fi
DB_NAME=$(grep -E '^DB_DATABASE=' "$ENV" | cut -d= -f2-)
DB_USER=$(grep -E '^DB_USERNAME=' "$ENV" | cut -d= -f2-)
DB_PASS=$(grep -E '^DB_PASSWORD=' "$ENV" | cut -d= -f2-)
DB_HOST=$(grep -E '^DB_HOST=' "$ENV" | cut -d= -f2- || echo "127.0.0.1")

LOCAL_DIR="/backups/codex"
DATE_STAMP=$(date +%F)
DUMP_PATH="$LOCAL_DIR/codex-$DATE_STAMP.sql.gz"

mkdir -p "$LOCAL_DIR"

echo "→ mysqldump $DB_NAME → $DUMP_PATH"
mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" \
    --single-transaction --routines --triggers \
    "$DB_NAME" | gzip > "$DUMP_PATH"

ls -lh "$DUMP_PATH"

echo "→ aws s3 sync $LOCAL_DIR → s3://codex-backups/mysql/"
aws s3 sync "$LOCAL_DIR" s3://codex-backups/mysql/ \
    --delete --storage-class STANDARD_IA

# Prune local backups > 30 days
find "$LOCAL_DIR" -name 'codex-*.sql.gz' -mtime +30 -delete

echo "✓ backup complete: $DUMP_PATH"
