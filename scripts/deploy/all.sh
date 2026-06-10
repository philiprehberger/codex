#!/usr/bin/env bash
# scripts/deploy/all.sh
#
# Top-level orchestrator. Runs the Laravel deploy then the Next.js
# deploy, fails the second if the first failed. Single command
# prevents half-deploys.
#
# Flags:
#   --target=staging|production  (default: production)
#   --first-deploy               sets CODEX_ALLOW_SEEDERS_IN_PRODUCTION
#                                for the single migrate:fresh --seed
#                                run on first deploy

set -euo pipefail

TARGET="production"
FIRST_DEPLOY="false"
for arg in "$@"; do
    case "$arg" in
        --target=*)
            TARGET="${arg#--target=}"
            ;;
        --first-deploy)
            FIRST_DEPLOY="true"
            ;;
    esac
done

case "$TARGET" in
    staging|production) ;;
    *) echo "✗ invalid target: $TARGET" >&2; exit 1 ;;
esac

echo "── codex deploy ($TARGET, first-deploy=$FIRST_DEPLOY)"

# Preflight
"$(dirname "$0")/preflight.sh"

# Laravel
echo "── 1/2 Laravel deploy"
FIRST_DEPLOY=$FIRST_DEPLOY TARGET=$TARGET "$(dirname "$0")/laravel.sh"

# Next.js
echo "── 2/2 Next.js deploy"
TARGET=$TARGET "$(dirname "$0")/next.sh"

echo "✓ codex deploy complete ($TARGET)"
