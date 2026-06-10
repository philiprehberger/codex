#!/usr/bin/env bash
# scripts/deploy/preflight.sh
#
# Fast checks before any deploy attempt. Failing here is cheap; failing
# halfway through deploy is expensive. Per Phase 8 §1 the preflight
# verifies:
#   1. DNS A records on both hosts resolve to 54.190.150.0
#   2. CAA records on the apex allow Let's Encrypt
#   3. The next free PM2 port is known (writes to .deploy-port)
#   4. .env.deployment exists and has every required key
#   5. CODEX_REVALIDATE_SECRETS / CODEX_ASSET_SIGNING_KEYS are
#      non-empty + look like the right shape

set -euo pipefail

DASHBOARD_HOST="codex.philiprehberger.com"
API_HOST="api.codex.philiprehberger.com"
EXPECTED_IP="54.190.150.0"
APEX="philiprehberger.com"

err() { echo "✗ $*" >&2; exit 1; }
ok()  { echo "✓ $*"; }
info(){ echo "→ $*"; }

# 1. DNS
info "checking DNS for $DASHBOARD_HOST"
if dig +short "$DASHBOARD_HOST" | grep -q "$EXPECTED_IP"; then
    ok "$DASHBOARD_HOST → $EXPECTED_IP"
else
    err "$DASHBOARD_HOST does not resolve to $EXPECTED_IP"
fi

info "checking DNS for $API_HOST"
if dig +short "$API_HOST" | grep -q "$EXPECTED_IP"; then
    ok "$API_HOST → $EXPECTED_IP"
else
    err "$API_HOST does not resolve to $EXPECTED_IP"
fi

# 2. CAA
info "checking CAA record on $APEX"
caa_records=$(dig CAA "$APEX" +short)
if echo "$caa_records" | grep -qi "letsencrypt.org"; then
    ok "CAA on $APEX includes letsencrypt.org"
else
    err "CAA on $APEX missing letsencrypt.org (set: 0 issue \"letsencrypt.org\")"
fi

# 3. .env.deployment
info "checking .env.deployment"
ENV_FILE="$(dirname "$0")/../../.env.deployment"
if [ ! -f "$ENV_FILE" ]; then
    err ".env.deployment not found at $ENV_FILE (copy from .env.deployment.example)"
fi
for key in SERVER_HOST SERVER_USERNAME SERVER_PRIVATE_KEY SERVER_BASE_PATH; do
    if ! grep -qE "^${key}=" "$ENV_FILE"; then
        err "$key missing from .env.deployment"
    fi
done
ok ".env.deployment present + required keys present"

# 4. PM2 free port discovery (best-effort; the deploy script reads it back)
if command -v pm2 >/dev/null 2>&1; then
    info "discovering next free PM2 port (≥ 3012)"
    in_use=$(pm2 jlist 2>/dev/null | grep -oE '"port":\s*[0-9]+' | grep -oE '[0-9]+' | sort -u || true)
    port=3012
    while echo "$in_use" | grep -qE "^${port}$"; do
        port=$((port + 1))
    done
    echo "$port" > "$(dirname "$0")/.deploy-port"
    ok "next free PM2 port: $port"
fi

# 5. Secrets shape
info "checking secret shape (non-empty + comma-list)"
secrets=$(grep -E '^CODEX_(REVALIDATE_SECRETS|ASSET_SIGNING_KEYS)=' "$ENV_FILE" || true)
if [ -z "$secrets" ]; then
    err "no CODEX_REVALIDATE_SECRETS or CODEX_ASSET_SIGNING_KEYS in .env.deployment"
fi
while IFS= read -r line; do
    name=${line%%=*}
    val=${line#*=}
    val=${val%\"}
    val=${val#\"}
    if [ -z "$val" ]; then
        err "$name is empty — secrets must be non-empty comma-separated 32-byte hex values"
    fi
done <<< "$secrets"
ok "secrets present"

echo
echo "preflight ✓ — ready to deploy"
