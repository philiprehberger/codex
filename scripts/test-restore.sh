#!/usr/bin/env bash
# scripts/test-restore.sh
#
# Drill the Phase 8 backup→restore pipeline. Runs in CI weekly + on demand
# from the local box. Exits non-zero if any step fails so the
# BetterStack heartbeat surfaces silent breakage.
#
# Steps:
#   1. migrate:fresh + seed against the live (test) DB
#   2. mysqldump the seeded DB to a temp .sql.gz
#   3. drop + recreate the test DB
#   4. restore the dump
#   5. migrate:status round-trips clean
#   6. run a single heatmap smoke query
#   7. cleanup
#
# A runbook that's never been exercised is a fiction. This is the exercise.

set -euo pipefail

DB_NAME="${DB_DATABASE:-codex_dev}"
DB_USER="${DB_USERNAME:-codex}"
DB_PASS="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

DUMP_PATH="$(mktemp --suffix=.sql.gz)"
RESTORE_DB="codex_restore_drill_$$"

cleanup() {
    rm -f "$DUMP_PATH"
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} \
        -e "DROP DATABASE IF EXISTS $RESTORE_DB" 2>/dev/null || true
}
trap cleanup EXIT

echo "→ 1/6  migrate:fresh --seed against $DB_NAME"
php artisan migrate:fresh --seed --force

echo "→ 2/6  mysqldump → $DUMP_PATH"
mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} \
    --single-transaction --routines --triggers "$DB_NAME" | gzip > "$DUMP_PATH"
ls -lh "$DUMP_PATH"

echo "→ 3/6  create scratch DB $RESTORE_DB"
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} \
    -e "CREATE DATABASE $RESTORE_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"

echo "→ 4/6  restore dump into $RESTORE_DB"
gunzip -c "$DUMP_PATH" | mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$RESTORE_DB"

echo "→ 5/6  migrate:status against the restored DB"
DB_DATABASE="$RESTORE_DB" php artisan migrate:status

echo "→ 6/6  smoke query against the restored DB"
DB_DATABASE="$RESTORE_DB" php artisan tinker --execute='
    $c = \App\Models\Capability::count();
    $p = \App\Models\Project::withoutGlobalScope(\App\Models\Scopes\RedactedScope::class)->count();
    echo "  capabilities=$c, projects=$p" . PHP_EOL;
    if ($c === 0 || $p === 0) { exit(1); }
'

echo "✓ restore drill complete"
