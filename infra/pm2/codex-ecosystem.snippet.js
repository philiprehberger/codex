// infra/pm2/codex-ecosystem.snippet.js
//
// Paste these into /var/www/ecosystem.config.js (the shared PM2 manifest
// that holds entries for every demo on the box). Two processes for
// Codex: the Next.js dashboard (codex-web) and the queue worker
// (codex-queue).
//
// IMPORTANT — codex-web is `instances: 1` PINNED. The /api/revalidate
// route uses an in-memory LRU rate-limit counter; cluster mode would
// silently multiply the effective limit by core count. When traffic
// justifies cluster mode, move the rate-limit counter to Redis as the
// same migration. Flagged in docs/upgrades.md.

module.exports = {
    apps: [
        // — Codex Next.js dashboard
        {
            name: 'codex-web',
            cwd: '/var/www/codex-web',
            script: 'server.js',                  // next.js standalone output
            instances: 1,                          // PINNED — see comment above
            exec_mode: 'fork',
            autorestart: true,
            max_memory_restart: '500M',
            env: {
                NODE_ENV: 'production',
                PORT: 3012,                        // set by preflight; sync with Apache vhost
                CODEX_API_INTERNAL_URL: 'http://127.0.0.1',
                NEXT_PUBLIC_CODEX_API_HOST: 'api.codex.philiprehberger.com',
                // CODEX_REVALIDATE_SECRETS read from /var/www/codex-web/.env
            },
            error_file: '/var/log/codex-web-error.log',
            out_file: '/var/log/codex-web-out.log',
        },

        // — Codex queue worker (bulk-tag revalidation + future jobs)
        {
            name: 'codex-queue',
            cwd: '/var/www/codex/current',
            script: 'artisan',
            args: 'queue:work --queue=default --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000',
            interpreter: 'php',
            instances: 1,
            exec_mode: 'fork',
            autorestart: true,
            max_memory_restart: '300M',
            env: {
                APP_ENV: 'production',
            },
            error_file: '/var/log/codex-queue-error.log',
            out_file: '/var/log/codex-queue-out.log',
        },
    ],
};
