#!/usr/bin/env bash
# scripts/deploy/next.sh
#
# Next.js dashboard deploy. Build locally → rsync the standalone output
# → pm2 reload codex-web.
#
# output: 'standalone' in next.config.ts means the build emits
# .next/standalone/ + .next/static/ + public/ → those are the
# entire production payload.

set -euo pipefail

TARGET="${TARGET:-production}"

# Pull config from web/.env.
set -a
. "$(dirname "$0")/../../web/.env"
set +a

REMOTE_PATH="${SERVER_DEST_PATH:-/var/www/codex-web}"
PM2_PROCESS="${SERVER_PM2_PROCESS:-codex-web}"
if [ "$TARGET" = "staging" ]; then
    REMOTE_PATH="${REMOTE_PATH%-staging}-staging"
    PM2_PROCESS="${PM2_PROCESS%-staging}-staging"
fi

REMOTE="$SERVER_USERNAME@$SERVER_HOST"
SSH_OPTS="-i $SERVER_PRIVATE_KEY -o StrictHostKeyChecking=accept-new"

info() { echo "→ $*"; }

cd "$(dirname "$0")/../../web"

info "npm ci + npm run build"
npm ci --no-audit --no-fund
npm run build

info "preparing rsync staging dir"
STAGING=".deploy-staging"
rm -rf "$STAGING"
mkdir -p "$STAGING"
cp -r .next/standalone/. "$STAGING/"
cp -r .next/static "$STAGING/.next/"
[ -d public ] && cp -r public "$STAGING/"

info "rsync → $REMOTE:$REMOTE_PATH"
ssh $SSH_OPTS "$REMOTE" "mkdir -p $REMOTE_PATH"
rsync -avz --delete -e "ssh $SSH_OPTS" "$STAGING/" "$REMOTE:$REMOTE_PATH/"

info "pm2 reload $PM2_PROCESS"
ssh $SSH_OPTS "$REMOTE" "pm2 reload $PM2_PROCESS || pm2 start /var/www/ecosystem.config.js --only $PM2_PROCESS"

rm -rf "$STAGING"
echo "✓ Next.js deploy complete"
