#!/usr/bin/env bash
# scripts/deploy/next.sh
#
# Next.js dashboard deploy. Build locally → rsync the standalone output
# → pm2 reload codex-web.
#
# output: 'standalone' in next.config.ts means the build emits
# .next/standalone/ + .next/static/ + public/ → those are the
# entire production payload.
#
# Discipline (Phase 8.6):
# - rsync runs with --delete (release dir is owned by the deploy) BUT
#   excludes .env so the production secrets file on the server is
#   preserved across redeploys.
# - On first deploy / fresh box, we check whether the remote .env is
#   present and write a minimal stub if missing. The stub contains the
#   non-secret defaults (PORT, NODE_ENV, internal URLs) and a TODO for
#   the secrets. The deploy refuses to start codex-web until the human
#   fills CODEX_REVALIDATE_SECRETS — without it, /api/revalidate is
#   permanently broken and the Filament observer's HMAC POSTs all 401.
# - After rsync, pm2 reload uses --update-env so any change to the
#   .env file is picked up by the running process.

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
err()  { echo "✗ $*" >&2; exit 1; }

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
# Belt-and-braces: drop any .env that snuck into standalone/.
rm -f "$STAGING/.env"

info "checking remote .env on $REMOTE:$REMOTE_PATH/"
ssh $SSH_OPTS "$REMOTE" "mkdir -p $REMOTE_PATH"
if ! ssh $SSH_OPTS "$REMOTE" "test -f $REMOTE_PATH/.env"; then
    info "remote .env missing — writing stub. THE DEPLOY WILL NOT BE COMPLETE UNTIL YOU FILL CODEX_REVALIDATE_SECRETS."
    ssh $SSH_OPTS "$REMOTE" "cat > $REMOTE_PATH/.env <<'EOF'
PORT=3012
NODE_ENV=production
CODEX_API_INTERNAL_URL=http://127.0.0.1
NEXT_PUBLIC_CODEX_API_HOST=api.codex.philiprehberger.com
# TODO: paste the value from /var/www/codex/shared/.env CODEX_REVALIDATE_SECRETS=
CODEX_REVALIDATE_SECRETS=
EOF
chmod 640 $REMOTE_PATH/.env"
    err ".env stub written at $REMOTE_PATH/.env — fill CODEX_REVALIDATE_SECRETS, then re-run deploy"
fi

info "rsync → $REMOTE:$REMOTE_PATH  (excludes .env)"
rsync -avz --delete --exclude=.env -e "ssh $SSH_OPTS" "$STAGING/" "$REMOTE:$REMOTE_PATH/"

info "pm2 reload $PM2_PROCESS --update-env"
ssh $SSH_OPTS "$REMOTE" "pm2 reload $PM2_PROCESS --update-env || pm2 start /var/www/ecosystem.config.js --only $PM2_PROCESS"

rm -rf "$STAGING"
echo "✓ Next.js deploy complete"
