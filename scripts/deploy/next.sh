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
#
# Build-time API dependency:
# `next build` prerenders SSG pages, which means it makes real HTTP
# calls to the API at CODEX_API_INTERNAL_URL (sourced from web/.env —
# typically http://127.0.0.1:8000 locally). The build is local, so the
# API must be reachable from the dev box. This script probes the URL
# and auto-spawns a transient `php artisan serve` if nothing answers,
# killing it on exit via a trap. If `composer dev` is already running,
# the probe finds it and the spawn is skipped.

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
# Clear Next.js fetch-cache so SSG queries fresh data each build.
# Without this, a build that runs after a backend shape change reuses
# the prior URL→response cache and renders pages with stale fields.
rm -rf .next/cache

# Ensure the Laravel API is reachable for prerender. Skip the spawn if
# `composer dev` (or any other process) already answers — otherwise
# fork an artisan serve scoped to this build and tear it down on exit.
API_URL="${CODEX_API_INTERNAL_URL:-http://127.0.0.1:8000}"
PROBE="$API_URL/api/v1/projects?per_page=1"
if curl -sf -o /dev/null --max-time 2 "$PROBE"; then
    info "API already reachable at $API_URL — reusing"
else
    # Parse the port from CODEX_API_INTERNAL_URL; default 8000 to match
    # `php artisan serve`'s default and the loopback convention.
    API_PORT=$(echo "$API_URL" | sed -nE 's|^https?://[^:/]+:([0-9]+).*|\1|p')
    API_PORT="${API_PORT:-8000}"
    info "spawning transient php artisan serve on 127.0.0.1:$API_PORT for prerender"
    ARTISAN_LOG=$(mktemp --suffix=.codex-deploy-artisan.log)
    ( cd .. && php artisan serve --host=127.0.0.1 --port="$API_PORT" >"$ARTISAN_LOG" 2>&1 ) &
    ARTISAN_PID=$!
    trap 'kill $ARTISAN_PID 2>/dev/null || true; rm -f "$ARTISAN_LOG"' EXIT
    for i in $(seq 1 15); do
        if curl -sf -o /dev/null --max-time 2 "$PROBE"; then break; fi
        sleep 1
    done
    if ! curl -sf -o /dev/null --max-time 2 "$PROBE"; then
        err "transient API failed to respond on 127.0.0.1:$API_PORT — see $ARTISAN_LOG"
    fi
fi

npm run build

info "preparing rsync staging dir"
STAGING=".deploy-staging"
rm -rf "$STAGING"
mkdir -p "$STAGING"
# Next.js standalone nests the runtime under web/ because the app source
# lives at web/ inside the monorepo. PM2 expects server.js at the
# deploy root (cwd: /var/www/codex-web), so flatten web/ to root.
cp -r .next/standalone/web/. "$STAGING/"
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
# pm2 lives under nvm on the remote; non-login ssh sessions don't source it.
ssh $SSH_OPTS "$REMOTE" "export PATH=\$(ls -d /home/ubuntu/.nvm/versions/node/*/bin 2>/dev/null | tail -1):\$PATH; pm2 reload $PM2_PROCESS --update-env || pm2 start /var/www/ecosystem.config.js --only $PM2_PROCESS"

rm -rf "$STAGING"
echo "✓ Next.js deploy complete"
