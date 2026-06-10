<?php

use App\Http\Responses\ProblemResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust EC2's NAT and any Apache-fronted proxy for X-Forwarded-For so
        // per-IP rate limit reads the real client IP. Cloudflare is not in
        // front of codex.* — direct A record to EC2 — so the proxy set is
        // currently just the Apache loopback path.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // RFC 7807 problem+json for any /api/* request or anything that
        // sends Accept: application/json. Phase 5 wires the full handler
        // and adds the unknown-filter-key + visibility-scope shapes.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return new ProblemResponse(
                status: 422,
                title: 'Invalid request',
                detail: 'The request body failed validation.',
                errors: $e->errors(),
            );
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return ProblemResponse::for($e);
        });
    })->create();
