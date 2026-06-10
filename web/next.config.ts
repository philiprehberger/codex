import type { NextConfig } from 'next';

const config: NextConfig = {
    output: 'standalone',
    reactStrictMode: true,

    // Defends against the inadvertent /_next/static/*.map exposure that
    // would otherwise let an attacker walk minified bundles back to
    // source. Source maps are uploaded directly to Sentry from the
    // deploy script and auth-gated by the Sentry org token.
    productionBrowserSourceMaps: false,

    // Trust the X-Forwarded-Host header from Apache so route URLs
    // generated server-side use the public host, not the loopback one.
    experimental: {
        // (intentionally empty — placeholder for Phase 7+ flags)
    },
};

export default config;
