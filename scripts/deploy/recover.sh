#!/usr/bin/env bash
# scripts/deploy/recover.sh
#
# Roll back to the previous release after a failed deploy.
#
# Per the plan §"Migration failure recovery" — automatic rollback is
# deliberately NOT wired into laravel.sh because DB state is too
# sensitive to undo without a human in the loop. This script is the
# documented manual path.

set -euo pipefail

set -a
. "$(dirname "$0")/../../.env.deployment"
set +a

SERVER_BASE_PATH="${SERVER_BASE_PATH:-/var/www/codex}"
REMOTE="$SERVER_USERNAME@$SERVER_HOST"
SSH_OPTS="-i $SERVER_PRIVATE_KEY -o StrictHostKeyChecking=accept-new"

RELEASES_DIR="$SERVER_BASE_PATH/releases"
CURRENT_LINK="$SERVER_BASE_PATH/current"

echo "→ inspecting releases"
ssh $SSH_OPTS "$REMOTE" "ls -1t $RELEASES_DIR" | head -5

CURRENT=$(ssh $SSH_OPTS "$REMOTE" "readlink $CURRENT_LINK | xargs basename")
echo "  current → $CURRENT"

PREV=$(ssh $SSH_OPTS "$REMOTE" "ls -1t $RELEASES_DIR" | grep -v "^$CURRENT$" | head -1)
if [ -z "$PREV" ]; then
    echo "✗ no previous release to roll back to" >&2
    exit 1
fi

echo "→ rollback target: $PREV"
read -r -p "  proceed? [y/N] " confirm
case "$confirm" in
    y|Y|yes|YES) ;;
    *) echo "aborted"; exit 0 ;;
esac

ssh $SSH_OPTS "$REMOTE" "ln -sfn $RELEASES_DIR/$PREV $CURRENT_LINK.tmp && mv -Tf $CURRENT_LINK.tmp $CURRENT_LINK"
ssh $SSH_OPTS "$REMOTE" "sudo /usr/sbin/apachectl graceful"
ssh $SSH_OPTS "$REMOTE" "pm2 reload codex-queue"

echo "✓ rolled back to $PREV"
echo
echo "DO NOT FORGET: if the deploy ran migrations, the DB is now ahead"
echo "of the code. Either:"
echo "  - apply the corresponding 'down' migrations manually (php artisan migrate:rollback)"
echo "  - or restore the latest backup per infra/RECOVERY.md → 'Restore from S3 backup'"
