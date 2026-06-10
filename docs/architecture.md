# Codex architecture

Read this together with `docs/api-conventions.md` (the API shape), `docs/invariants.md` (the cross-cutting rules), and `infra/RECOVERY.md` (the runbook).

## High-level shape

```
                            codex.philiprehberger.com
                                      │
                               ┌──────┴───────┐
                               │  Next.js 16  │  PM2 codex-web · instances: 1
                               │  Dashboard   │  port 3012
                               │  Heatmap     │  output: standalone
                               │  Gap report  │  productionBrowserSourceMaps: false
                               └──────┬───────┘
                                      │  SSR fetch — loopback URL +
                                      │  injected Host header
                                      │  (web/src/lib/codex-api.ts)
                                      │
                       api.codex.philiprehberger.com
                                      │
                               ┌──────┴────────┐
                               │  Laravel 13   │  Apache + mod_php 8.3
                               │  Read API +   │  RFC 7807 problem-detail
                               │  Filament v5  │  CORS allow-list
                               │  admin /admin │  per-host CSP +
                               │               │  per-request nonce on /admin
                               └──────┬────────┘
                                      │
                                ┌─────┴─────┐
                                │  MySQL 8  │  database: codex
                                │           │  ~20 tables (v1+v2+pivots)
                                └───────────┘

  Sidecars:
  • PM2 codex-queue — queue:work, database driver, --max-time/--max-jobs recycled
  • Cron — nightly invariants, audit, slug collisions, export, mysqldump, audit-log archive
  • Plausible — self-hosted on the EC2 box (no cookies, no GDPR banner)
  • Sentry + BetterStack — free tier; release tagging from the deploy script
```

## Why mod_php, not PHP-FPM

The EC2 box co-tenants WordPress demos that depend on Apache `mpm_prefork` + `mod_php`. Switching the box to `mpm_event` + PHP-FPM for one project breaks every WP demo.

The "production-shape Laravel uses PHP-FPM" critique is correct in a greenfield. Here the constraint is host co-tenancy. If the WP demos retire, this is the first thing to revisit (documented in `docs/upgrades.md`).

## Why no PITR (point-in-time recovery)

Phase 1 ships with nightly `mysqldump` → S3 (24h RPO, 1h RTO). Binlog shipping + replay is real operational weight for a single-curator dataset where the worst-case loss is a day of tagging.

If buyer signal in Phase 2 funds the work, MySQL binlog → S3 + a `recover-to-timestamp.sh` runbook is the documented next step. Until then, 24h RPO is the accepted floor — called out here so it's not a surprise.

## Caching strategy

`Cache::tags()` does not work on the `file` or `database` drivers. The plan's revised Phase 5 spec uses the `database` driver + named keys + explicit `Cache::forget()`. Four named keys cover every public surface:

- `codex:heatmap`
- `codex:reports:gaps`
- `codex:reports:bullets`
- `codex:search:index`

Invalidation goes through `App\Services\CacheInvalidator::forgetReports()` which iterates `config('codex.cache.report_keys')` and forgets each. Adding a fifth cached endpoint = append to the config; the helper picks it up automatically.

On any Filament write the request-scoped `RevalidationBuffer` accumulates tags, the `terminating()` callback in `AppServiceProvider` flushes them (local cache + HMAC-signed POST to the Next.js host) once at the end of the request. Bulk operations (>10 tags) push to the database queue via `RevalidateCacheJob` so admin returns immediately.

## Cost projection at Phase 1 traffic

| Component | Cost |
|-----------|------|
| S3 nightly mysqldump (~5-10MB × 30 days × STANDARD_IA) | $0.005/mo |
| S3 nightly access-log shipping (~50MB × 90 days × STANDARD_IA) | $0.10/mo |
| S3 monthly audit-log archive (~1MB × 12 mo × STANDARD_IA) | $0.002/mo |
| S3 nightly portfolio JSON export (~30KB × 30 days × STANDARD_IA) | trace |
| Sentry free tier | $0 (≤ 5k events/mo) |
| BetterStack free tier | $0 (≤ 10 monitors) |
| Plausible self-hosted | $0 marginal (existing EC2 box) |
| EC2 + Apache + MySQL | $0 marginal (co-tenanted on existing box) |
| **Total** | **< $0.50/mo** |

Re-project if Sentry or BetterStack tier up. Tier-up triggers are documented in `docs/upgrades.md`.

## Freshness SLO

Alongside the 99.5% availability SLO, Codex commits to a **freshness SLO**: cache-bust latency (Filament write → public page reflects change) under 5 min p99.

Measured by the staging admin-load test (Phase 7 perf budget). Breaches are tracked as freshness incidents in `infra/RECOVERY.md`, distinct from uptime incidents.

## Plausible memory budget

Plausible bundles ClickHouse + PostgreSQL + the Elixir app. On the co-tenanted EC2 box, the resident-set hit is ~1.5-2GB. Phase 8 §10 includes a `free -h` + `df -h` check before flipping production traffic; if RSS headroom is under 1GB, Plausible moves to a managed tier rather than crowding MySQL.

Plausible's systemd unit pins `MemoryMax=2G` so a runaway ClickHouse query can't OOM-kill MySQL on the same host — Plausible dies first, BetterStack alerts, MySQL stays up.

## Why CSP nonce on /admin

Filament v5 emits inline scripts and styles (Livewire boot, Alpine data attributes, etc). A strict CSP that forbids `'unsafe-inline'` breaks the panel.

The supported escape hatch is a per-request nonce that Filament writes onto every inline tag via `FilamentAsset::registerScriptData(['cspNonce' => $nonce])`. `App\Http\Middleware\AdminCspMiddleware` generates the nonce, attaches it via `FilamentAsset`, and emits the CSP header — keeping `unsafe-inline` off while the panel still works.

The dashboard host (`codex.philiprehberger.com`) gets a different, static CSP via Apache headers (`infra/apache/csp-dashboard.conf`). Two policies, two paths.

## Why `instances: 1` on codex-web

The `/api/revalidate` route uses an in-memory LRU rate-limit counter. Cluster mode (`instances: 'max'`) would silently multiply the effective limit by core count. PM2 `instances: 1` is pinned in `infra/pm2/codex-ecosystem.snippet.js` with a comment.

When traffic justifies cluster mode (unlikely in Phase 1), the rate-limit counter moves to Redis as the same migration. Flagged in `docs/upgrades.md`.

## Loopback Host header — load-bearing

The Next.js dashboard SSR fetches Laravel via `http://127.0.0.1` (Apache binds on :80 locally). **Without an explicit `Host: api.codex.philiprehberger.com` header on every request, Apache picks the default vhost and SSR silently 404s.**

The wrapper at `web/src/lib/codex-api.ts` injects the header on every call. An ESLint `no-restricted-imports` rule blocks raw `fetch()` in pages so this discipline is enforced at the linter, not at review time.

Laravel-side traps the loopback path opens:

- `App\Http\Middleware\TrustHosts` must allow-list `api.codex.philiprehberger.com` — otherwise Laravel 422s on the injected Host header.
- The api vhost's `:80 → :443` certbot redirect must carve out `127.0.0.1` — otherwise loopback fetches 301 into TLS.
- `URL::forceScheme('https')` runs in `AppServiceProvider::boot()` so absolute URLs in payloads always emit `https://api.codex.philiprehberger.com/...` even when the request arrived over loopback http.
- `config('app.url')` is `https://api.codex.philiprehberger.com` (not the loopback URL) so `URL::signedRoute()` reads the right host.

The Phase 8 DoD asserts `curl -H 'Host: api.codex.philiprehberger.com' http://127.0.0.1/up` returns 200, not 301.

## File / directory layout

```
~/projects/codex/
├── app/
│   ├── Actions/         MergeCapability, SetPrimaryTag
│   ├── Console/Commands/ codex:* (8 commands)
│   ├── Filament/        Resources (12) + Widgets
│   ├── Http/
│   │   ├── Controllers/Api/V1/  ListProjects, Show*, Heatmap, Reports, …
│   │   ├── Middleware/AdminCspMiddleware
│   │   ├── Requests/Api/V1/ListProjectsRequest
│   │   └── Responses/ProblemResponse
│   ├── Jobs/            RevalidateCacheJob
│   ├── Models/          ULID-PK models + Pivots + Scopes\RedactedScope
│   ├── Observers/       RevalidationObserver
│   ├── Providers/       AppServiceProvider, SeederGuardServiceProvider, AdminPanelProvider
│   ├── Rules/           SlugRule
│   ├── Services/        AssetSigner, RevalidateClient, RevalidationBuffer, CacheInvalidator
│   └── Support/         BinaryCollation
├── config/codex.php     Vocab caps, cache keys, asset signing, revalidation, audit retention
├── database/
│   ├── migrations/      ~20 migrations, utf8mb4_bin pinned via BinaryCollation
│   ├── factories/       12 factories
│   └── seeders/         CapabilitySeeder + Tag + DemoProjects + Packages + ClientWork
├── docs/                architecture.md (this), api-conventions.md, invariants.md, upgrades.md
├── infra/
│   ├── apache/          per-host vhosts + CSP includes + security-headers-common
│   ├── backup/          mysqldump-to-s3.sh
│   ├── cron/            codex-crontab
│   ├── pm2/             codex-ecosystem.snippet.js
│   └── RECOVERY.md
├── routes/              api.php (v1 + 410 fallback), web.php (/up/diagnostics)
├── scripts/
│   ├── deploy/          all.sh, preflight.sh, laravel.sh, next.sh, recover.sh
│   └── test-restore.sh
├── tests/Feature/       50 PHPUnit tests / 168 assertions
└── web/                 Next.js 16 dashboard
    ├── app/             11 routes
    ├── src/
    │   ├── components/  Heatmap
    │   └── lib/         codex-api.ts (the one allowed fetch wrapper)
    ├── tests/           Vitest (codex-api, /api/revalidate)
    └── vitest.config.ts
```
