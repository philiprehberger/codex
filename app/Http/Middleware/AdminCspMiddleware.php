<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-request CSP nonce middleware for /admin.
 *
 * Filament v5 emits inline scripts and styles (Livewire boot, Alpine
 * data attributes, tooltip handlers, etc). A strict CSP that forbids
 * 'unsafe-inline' breaks the panel; the supported escape hatch is a
 * per-request nonce that Filament writes onto every inline tag.
 *
 * Wiring:
 *  1. Generate a 16-byte base64 nonce.
 *  2. Share via FilamentAsset::registerScriptData(['cspNonce' => …]) so
 *     Filament's Blade partials pick it up.
 *  3. Emit the Content-Security-Policy header with script-src and
 *     style-src tied to the nonce + 'strict-dynamic'. Apache's vhost
 *     leaves /admin alone for CSP (per infra/apache/security-headers.conf
 *     in Phase 8) so this is the sole policy for /admin paths.
 *
 * The dashboard host (codex.philiprehberger.com) gets a different,
 * static CSP via Apache headers. Two policies, two paths.
 */
class AdminCspMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);

        // Make the nonce available to Filament's Blade partials. Filament
        // v5 reads the `cspNonce` script-data key and stamps it onto its
        // rendered inline <script> + <style> tags.
        FilamentAsset::registerScriptData(['cspNonce' => $nonce]);

        /** @var Response $response */
        $response = $next($request);

        $policy = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'upgrade-insecure-requests',
        ]);

        $response->headers->set('Content-Security-Policy', $policy);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }
}
