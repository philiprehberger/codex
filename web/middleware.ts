import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

/**
 * Per-request CSP with a nonce.
 *
 * The /heatmap and /resume-bullets pages ship client components, which
 * means Next.js emits small inline <script> tags for hydration data.
 * Those need a CSP nonce to be allowed under script-src; whitelisting
 * 'unsafe-inline' would defeat the purpose of the policy.
 *
 * Pattern from the Next.js docs (Configuring → CSP):
 *  1. Generate a fresh base64 nonce per request.
 *  2. Inject it into the request headers as `x-nonce` so the framework
 *     applies it to its own inline scripts when rendering the page.
 *  3. Set the Content-Security-Policy response header with the same
 *     nonce inside script-src.
 *
 * Apache's vhost-level CSP is configured via `Header always setifempty`
 * (infra/apache/csp-dashboard.conf) so this per-request policy wins; the
 * Apache fallback only fires if the middleware is bypassed entirely.
 */
export function middleware(request: NextRequest) {
    const nonce = Buffer.from(crypto.randomUUID()).toString('base64');

    const csp = [
        "default-src 'self'",
        // 'self' allows /_next/static/*; the nonce covers Next.js's
        // inline hydration scripts. Plausible is the analytics origin.
        `script-src 'self' 'nonce-${nonce}' https://plausible.philiprehberger.com`,
        "style-src 'self'",
        // README markdown carries badges and screenshots — allow the
        // common GitHub + badge hosts. Camo is GitHub's image proxy
        // that rewrites external <img> srcs to a same-origin CDN.
        "img-src 'self' https://api.codex.philiprehberger.com https://github.com https://*.githubusercontent.com https://img.shields.io https://badge.fury.io data:",
        "font-src 'self'",
        "connect-src 'self' https://api.codex.philiprehberger.com https://plausible.philiprehberger.com",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        'upgrade-insecure-requests',
    ].join('; ');

    const requestHeaders = new Headers(request.headers);
    requestHeaders.set('x-nonce', nonce);
    requestHeaders.set('Content-Security-Policy', csp);

    const response = NextResponse.next({
        request: { headers: requestHeaders },
    });
    response.headers.set('Content-Security-Policy', csp);
    return response;
}

export const config = {
    matcher: [
        // Skip static assets + image optimizer + favicon — they don't
        // render pages and don't need a per-request CSP. The /api/*
        // routes (just /api/revalidate today) also skip because they
        // return JSON, not HTML.
        {
            source: '/((?!api|_next/static|_next/image|favicon.ico).*)',
            missing: [
                { type: 'header', key: 'next-router-prefetch' },
                { type: 'header', key: 'purpose', value: 'prefetch' },
            ],
        },
    ],
};
