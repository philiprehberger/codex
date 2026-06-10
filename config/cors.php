<?php

/*
 * Codex public read API CORS allow-list.
 *
 * The Next.js dashboard at codex.philiprehberger.com is the only first-
 * party browser caller. Server-side fetches from the dashboard go via
 * the loopback URL + Host header injection (Phase 5 spec) so CORS
 * doesn't apply to that path; this config covers any browser-side
 * fetch that the Phase 2 SDK story may eventually need.
 *
 * Per plan §"CORS" — for non-allow-listed origins we OMIT the
 * Access-Control-Allow-Origin header (browser-spec rejection) rather
 * than returning 403. A 403 surfaces as an opaque CORS error in
 * DevTools and breaks Phase 2 SDK consumers probing a wrong subdomain;
 * omitting ACAO lets the browser block the response with the proper
 * signal.
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

    'allowed_origins' => [
        'https://codex.philiprehberger.com',
        'http://localhost:3012',
        'http://localhost:3000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    'max_age' => 600,

    'supports_credentials' => false,
];
