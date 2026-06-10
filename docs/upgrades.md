# Codex upgrade triggers

Pre-recorded future migrations, not blockers. Each section describes a known scale-up trigger, the work it implies, and the rough effort estimate.

## Filament 5 → 6 (when v6 ships)

**Current**: `^5.6`. v5 has first-party MFA (`AppAuthentication::make()->recoverable()`).

**Watch**: Filament v6 release notes for breaking changes to `Resource`, `Schema`, or the MFA contracts. The `app_authentication_secret` + `app_authentication_recovery_codes` column names are part of v5's `InteractsWithAppAuthentication` contract; v6 may rename them.

**Effort**: 2-4 hours if no schema changes; +1 day if column renames or contract changes.

## Cache pressure → Redis

**Current**: `database` cache driver. Four named keys (`codex:heatmap`, `codex:reports:gaps`, `codex:reports:bullets`, `codex:search:index`) cover every public surface.

**Trigger**: When the cached endpoints expand past ~10 keys, or when Cache::tags() becomes a requirement, move to Redis.

**Side effect of moving**: the in-memory rate-limit counter in `web/app/api/revalidate/route.ts` also moves to Redis at the same time. Both are per-process today.

**Effort**: 1 day — install Redis on the EC2 box, swap the driver, re-verify the `CacheInvalidator` forgets the right keys.

## WP demos retire → PHP-FPM

**Current**: mod_php + `mpm_prefork` for co-tenancy with the WordPress demos.

**Trigger**: when the last WP demo is decommissioned, the box can switch to `mpm_event` + PHP-FPM.

**Side effect**: per-request memory drops, concurrent throughput improves, opcache-based deploys become simpler. No Codex code changes needed — Laravel runs on both.

**Effort**: 2-3 hours including vhost reshape.

## SDK story → revisit 422-on-unknown-key

**Current**: `config('codex.api.strict_query_keys') = true` → 422 on any query key not in the allow-list.

**Trigger**: Phase 2 ships public SDKs (TS, Python). Industry-standard SDKs (Stripe, GitHub, AWS) silently ignore unknown query keys so older clients can roll forward. Flip the config flag at the same time.

**Effort**: 30 minutes — flip the env, regenerate cached config, add a migration note to the SDK changelogs.

## Paratest flake → drop Paratest

**Current**: phpunit.xml + composer scripts set up for serial PHPUnit. Paratest is in `require-dev` but not wired into CI.

**Trigger**: when CI test runs cross 5 minutes consistently, wire Paratest with per-process DB schemas. If the per-process plumbing turns out flakier than the time savings justify, drop Paratest and accept the serial run.

**Effort**: 4-6 hours including phpunit.xml DB_DATABASE template + bootstrap re-create.

## codex-web cluster mode → move rate-limit counter to Redis

**Current**: `instances: 1` PINNED on codex-web because the `/api/revalidate` rate-limit counter is per-process in-memory.

**Trigger**: when Codex sees > 1000 RPS on the dashboard (very unlikely at Phase 1 traffic), cluster mode becomes worthwhile.

**Side effect**: rate-limit counter migrates to Redis (same migration as the cache pressure trigger above — do both at once).

**Effort**: 4-6 hours.

## Loopback HTTPS sidecar → rewrite the Next.js fetch wrapper

**Current**: `web/src/lib/codex-api.ts` targets `http://127.0.0.1` with an injected Host header. Apache vhost on :80 handles the loopback path with a redirect carve-out for `REMOTE_ADDR == 127.0.0.1`.

**Trigger**: if production policy ever requires HTTPS-everywhere internally (e.g. PCI-DSS audit), set up a loopback TLS sidecar (stunnel or nginx local-cert), then rewrite the fetch wrapper to use `https://127.0.0.1` + `--cacert` against the loopback cert. Drop the Host header injection — the cert SAN matches.

**Effort**: 1 day including cert lifecycle automation.

## PITR (point-in-time recovery)

**Current**: Nightly mysqldump → S3, 24h RPO, 1h RTO.

**Trigger**: buyer signal funds the operational overhead (a single-curator dataset where the worst-case loss is a day of tagging doesn't justify it on its own).

**Plan**: MySQL binlog shipping → S3 with one log file per hour. Recovery via `recover-to-timestamp.sh` runbook that downloads the latest mysqldump + replays binlogs to the target timestamp.

**Effort**: 2-3 days.

## HSTS preload

**Current**: HSTS `max-age=31536000; includeSubDomains` (no `preload`).

**Trigger**: after auditing every existing + planned subdomain on `philiprehberger.com` for HTTPS readiness, submit to [hstspreload.org](https://hstspreload.org) and then add `preload` to the header.

**Caveat**: preload is hard to undo. Browsers cache the policy for 6-12 months minimum.

**Effort**: half a day for the audit + submission, then days/weeks of waiting for inclusion.

## OG image generator (Satori + Edge runtime)

**Current**: Static reuse of the project's first existing screenshot (`project_assets.display_order=0`), pre-cropped to 1200×630 at upload time by the Filament asset processor.

**Trigger**: buyer engagement justifies the cold-start cost on first share + the new failure mode (LinkedIn preview breaks if Edge route 500s).

**Effort**: 1 day for the Satori template + a Phase 2 migration to drop the pre-cropped `og_path` column (which becomes redundant once generated images cover every case).

## Per-package portfolio representation

**Current**: Packages seeded as 25 "cluster-per-language" rows (PHP/Laravel — Feature Flags, TypeScript — Caching, Go — Resilience, …). The 630 npm/Composer packages are not individually rendered.

**Trigger**: buyer signal in Phase 2 supports a per-package drill-down (search for "DateTime parsing helper" and find the exact package).

**Effort**: 1-2 weeks — add a `packages` sibling table to `projects`, ingestion script reading the package registries (Packagist + npm), per-package detail pages on the dashboard. Significant scope creep so the trigger bar is high.
