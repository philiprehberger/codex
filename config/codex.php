<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Internal and public API URLs
    |--------------------------------------------------------------------------
    |
    | The Next.js dashboard server-fetches the Laravel API at build /
    | revalidate time. It targets the loopback URL with an explicit
    | Host: api.codex.philiprehberger.com header injected by the fetch
    | wrapper at web/src/lib/codex-api.ts. The public URL is the only one
    | that should end up in payloads (asset paths, signed-route URLs).
    |
    */
    'internal_api_url' => env('CODEX_API_INTERNAL_URL', 'http://127.0.0.1'),
    'public_api_url' => env('CODEX_PUBLIC_API_URL', 'https://api.codex.philiprehberger.com'),

    /*
    |--------------------------------------------------------------------------
    | Seeder safety
    |--------------------------------------------------------------------------
    |
    | Two layers of production guard: BaseSeeder's constructor and
    | SeederGuardServiceProvider's boot listener. Both check this flag.
    | Set to true only for the first-deploy seed via the deploy script's
    | --first-deploy flag.
    |
    */
    'seeders' => [
        'allow_in_production' => env('CODEX_ALLOW_SEEDERS_IN_PRODUCTION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vocabulary caps
    |--------------------------------------------------------------------------
    |
    | Banner appears in each Filament list view at warn; creation blocked
    | at cap. Asserted by every *Seeder before run. Capabilities are the
    | heatmap row count — the scannable-in-3s test, paper-prototype
    | verified before Phase 4 commits.
    |
    */
    'vocabulary' => [
        'capabilities' => ['warn' => 60, 'cap' => 80],
        'technologies' => ['warn' => 80, 'cap' => 120],
        'industries' => ['warn' => 20, 'cap' => 30],
        'architectures' => ['warn' => 10, 'cap' => 20],
        'deliverables' => ['warn' => 10, 'cap' => 20],
        'design_styles' => ['warn' => 10, 'cap' => 20],
        'project_tags' => ['warn' => 40, 'cap' => 80],
    ],

    /*
    |--------------------------------------------------------------------------
    | Visibility / asset-redaction window
    |--------------------------------------------------------------------------
    |
    | When a project flips from public to redacted/private, the old asset
    | route returns 410 Gone for this many days so edge caches evict the
    | URL rather than 404-retrying. After the window, the route returns
    | 404 for clean garbage collection.
    |
    */
    'visibility' => [
        'gone_window_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache report keys (Phase 5)
    |--------------------------------------------------------------------------
    |
    | Filament observer calls CacheInvalidator::forgetReports() on writes;
    | the helper iterates this list and forgets every key. Centralised so
    | adding a fifth cached endpoint updates one place. Phase 7 test
    | asserts the helper forgets every key listed here.
    |
    */
    'cache' => [
        'report_keys' => [
            'codex:heatmap',
            'codex:reports:gaps',
            'codex:reports:bullets',
            'codex:search:index',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit-log retention
    |--------------------------------------------------------------------------
    |
    | codex:archive-audit-log archives rows older than this many days to
    | the monthly JSONL.gz on S3 and deletes them from the live table.
    | merge_capability / unmerge_capability / visibility_change rows are
    | exempt — they are the moderation provenance and retained forever.
    |
    */
    'audit_log' => [
        'live_retention_days' => 90,
        'exempt_actions' => [
            'merge_capability',
            'unmerge_capability',
            'visibility_change',
            'force_delete',
            'reset_2fa',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Revalidation — Filament observer → Next.js dashboard
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of HMAC secrets. The FIRST entry is the active
    | write key; all entries are accepted on the verifier side (the
    | Next.js /api/revalidate handler) for no-flap rotation. See
    | infra/RECOVERY.md (Phase 8) for the two-step rotation procedure.
    |
    | next_revalidate_url targets the Next.js dashboard origin (loopback
    | in production via Apache + Host header injection per Phase 5/6).
    | Phase 6 will set this to http://127.0.0.1:<port> when the dashboard
    | comes up; Phase 1-3 leaves it null and the RevalidateClient becomes
    | a no-op (logs a warning), which is correct behaviour.
    |
    | queue_threshold: bulk operations beyond this tag count get pushed
    | onto the database queue (codex-queue PM2 process) instead of
    | inline-fired during the request lifecycle.
    |
    */
    'revalidate' => [
        'secrets' => env('CODEX_REVALIDATE_SECRETS'),
        'next_revalidate_url' => env('CODEX_NEXT_REVALIDATE_URL'),
        'queue_threshold' => env('CODEX_REVALIDATE_QUEUE_THRESHOLD', 10),
    ],

    'next_revalidate_url' => env('CODEX_NEXT_REVALIDATE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Asset signing (Phase 3/5/6)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of HMAC secrets, same shape as the revalidate
    | secrets but a SEPARATE rotation path so leaking a signature doesn't
    | force an APP_KEY rotation (which would invalidate sessions + every
    | encrypted-at-rest column).
    |
    */
    'asset_signing' => [
        'keys' => env('CODEX_ASSET_SIGNING_KEYS'),
        'ttl' => env('CODEX_ASSET_SIGNING_TTL', 7200), // 2h — exceeds revalidate=3600 SSR cache window
    ],

    /*
    |--------------------------------------------------------------------------
    | API strict-query-keys (Phase 5)
    |--------------------------------------------------------------------------
    |
    | When true, /api/v1/* endpoints 422 on any query key not in their
    | FormRequest allow-list. When false (Phase 2 flip after public SDK
    | story exists), unknown keys are silently ignored to match the
    | Stripe / GitHub / AWS SDK convention. Default true for the
    | dashboard-only consumer.
    |
    */
    'api' => [
        'strict_query_keys' => env('CODEX_API_STRICT_QUERY_KEYS', true),
    ],

];
