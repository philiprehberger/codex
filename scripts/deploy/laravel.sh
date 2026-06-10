#!/usr/bin/env bash
# scripts/deploy/laravel.sh
#
# Atomic-release Laravel deploy. Build the new release in a sibling
# directory, swap a symlink, restart workers. Failed migrations
# preserve the previous release; rollback is one symlink swap.
#
# Per Phase 8 §8 — these are the production-shaped steps:
#   1. Build vendor/ locally + tarball the repo
#   2. rsync the tarball + extract to releases/<timestamp>
#   3. Symlink shared/.env + shared/storage
#   4. php artisan migrate --force (PRESERVE release on failure)
#   5. php artisan config:cache route:cache view:cache event:cache
#   6. php artisan optimize
#   7. Swap current symlink
#   8. apachectl graceful
#   9. pm2 reload codex-queue (picks up code changes)
#  10. Sentry release tagging
#  11. Prune old releases

set -euo pipefail

TARGET="${TARGET:-production}"
FIRST_DEPLOY="${FIRST_DEPLOY:-false}"

# Pull config from .env.deployment.
set -a
. "$(dirname "$0")/../../.env.deployment"
set +a

SERVER_BASE_PATH="${SERVER_BASE_PATH:-/var/www/codex}"
if [ "$TARGET" = "staging" ]; then
    SERVER_BASE_PATH="${SERVER_BASE_PATH%-staging}-staging"
fi

RELEASES_DIR="$SERVER_BASE_PATH/releases"
SHARED_DIR="$SERVER_BASE_PATH/shared"
CURRENT_LINK="$SERVER_BASE_PATH/current"
RELEASE_NAME=$(date +%Y%m%d%H%M%S)
RELEASE_PATH="$RELEASES_DIR/$RELEASE_NAME"

REMOTE="$SERVER_USERNAME@$SERVER_HOST"
SSH_OPTS="-i $SERVER_PRIVATE_KEY -o StrictHostKeyChecking=accept-new"

info() { echo "→ $*"; }
err()  { echo "✗ $*" >&2; exit 1; }

# 1. Build vendor locally
info "composer install (no-dev, optimize)"
composer install --no-dev --no-progress --prefer-dist --optimize-autoloader

# 2. Tarball
info "creating release tarball"
TARBALL=$(mktemp --suffix=.tar.gz)
tar --exclude='./node_modules' --exclude='./web/node_modules' --exclude='./web/.next' \
    --exclude='./.git' --exclude='./tests' --exclude='./.scratch' --exclude='./.idea' \
    --exclude='./storage/logs/*.log' \
    -czf "$TARBALL" -C "$(dirname "$0")/../.." .

# 3. Remote: create release dir + extract
info "rsync → $REMOTE:$RELEASE_PATH"
ssh $SSH_OPTS "$REMOTE" "mkdir -p $RELEASE_PATH $SHARED_DIR/storage/app/private/projects $SHARED_DIR/storage/app/public/projects $SHARED_DIR/storage/framework/{cache,sessions,views} $SHARED_DIR/storage/logs $SHARED_DIR/storage/exports $RELEASES_DIR"
scp $SSH_OPTS "$TARBALL" "$REMOTE:$RELEASE_PATH/release.tar.gz"
ssh $SSH_OPTS "$REMOTE" "cd $RELEASE_PATH && tar xzf release.tar.gz && rm release.tar.gz"
rm -f "$TARBALL"

# 4. Wire shared/.env + shared/storage
info "wiring shared/.env and shared/storage symlinks"
ssh $SSH_OPTS "$REMOTE" "
    cd $RELEASE_PATH
    ln -sfn $SHARED_DIR/.env .env
    rm -rf storage
    ln -sfn $SHARED_DIR/storage storage
"

# 5. Migrate — PRESERVE release on failure
info "running migrations"
SEED_FLAG=""
if [ "$FIRST_DEPLOY" = "true" ]; then
    info "first-deploy mode: migrate:fresh --seed allowed"
    SEED_FLAG="--seed"
    export CODEX_ALLOW_SEEDERS_IN_PRODUCTION=true
fi
if ! ssh $SSH_OPTS "$REMOTE" "cd $RELEASE_PATH && CODEX_ALLOW_SEEDERS_IN_PRODUCTION=$CODEX_ALLOW_SEEDERS_IN_PRODUCTION php artisan migrate --force $SEED_FLAG"; then
    echo "✗ migration failed — release $RELEASE_NAME preserved for inspection" >&2
    echo "→ run: ssh $REMOTE 'cd $RELEASE_PATH && php artisan migrate:status'" >&2
    echo "→ see: infra/RECOVERY.md → 'Migration failed mid-deploy'" >&2
    exit 1
fi

# 6. Cache + optimize
info "cache + optimize"
ssh $SSH_OPTS "$REMOTE" "
    cd $RELEASE_PATH
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan optimize
"

# 7. Swap current symlink (the actual atomic moment)
info "swapping current symlink → $RELEASE_NAME"
ssh $SSH_OPTS "$REMOTE" "ln -sfn $RELEASE_PATH $CURRENT_LINK.tmp && mv -Tf $CURRENT_LINK.tmp $CURRENT_LINK"

# 8. Apache graceful reload
info "apachectl graceful"
ssh $SSH_OPTS "$REMOTE" "sudo /usr/sbin/apachectl graceful"

# 9. Queue worker reload (picks up new code)
info "pm2 reload codex-queue"
ssh $SSH_OPTS "$REMOTE" "pm2 reload codex-queue || pm2 start /var/www/ecosystem.config.js --only codex-queue"

# 10. Sentry release tagging (best-effort)
info "Sentry release tag"
RELEASE_SHA=$(git rev-parse --short HEAD)
if command -v npx >/dev/null 2>&1 && [ -n "${SENTRY_AUTH_TOKEN:-}" ]; then
    npx --yes @sentry/cli releases new "codex@$RELEASE_SHA" || true
    npx --yes @sentry/cli releases set-commits "codex@$RELEASE_SHA" --auto || true
    npx --yes @sentry/cli releases finalize "codex@$RELEASE_SHA" || true
fi

# 11. Prune old releases (keep RELEASES_TO_KEEP)
info "pruning old releases (keeping ${RELEASES_TO_KEEP:-5})"
ssh $SSH_OPTS "$REMOTE" "
    cd $RELEASES_DIR
    ls -1t | tail -n +$((${RELEASES_TO_KEEP:-5} + 1)) | xargs -r rm -rf
"

echo "✓ Laravel deploy complete: $RELEASE_NAME"
