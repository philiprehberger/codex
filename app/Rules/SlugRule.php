<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Route;

/**
 * Slug validation shared by Filament + API + seeders. Enforces:
 *  - kebab-case [a-z0-9-]+, ≥ 3 chars, no leading/trailing/double hyphen
 *  - reserved-word rejection derived from Route::getRoutes() at validation
 *    time (so a route added in Phase 6 that shadows a previously-valid
 *    slug is caught at write time)
 *  - hard-coded fallback used when the router isn't bootable (raw seeders,
 *    schema migrations) — covers framework names not in the dashboard
 *    route table
 *
 * Drift between the route-derived list and a seeded slug is also caught
 * by the nightly codex:audit-slug-collisions cron.
 */
class SlugRule implements ValidationRule
{
    /**
     * Framework and dashboard names that must always be reserved, even
     * before the router boots. Kept short — the live route table is the
     * authoritative source at runtime.
     */
    public const FALLBACK_RESERVED = [
        'new', 'admin', 'api', 'storage', '_next', 'livewire',
        'sanctum', 'nova-api', 'up', 'health',
        'heatmap', 'gaps', 'projects', 'capabilities',
        'resume-bullets', 'about', 'search',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Slug must be a non-empty string.');
            return;
        }

        if (strlen($value) < 3) {
            $fail('Slug must be at least 3 characters.');
            return;
        }

        if (strlen($value) > 120) {
            $fail('Slug must be at most 120 characters.');
            return;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $value)) {
            $fail('Slug must be lowercase kebab-case (a-z, 0-9, hyphens; no leading/trailing/double hyphen).');
            return;
        }

        if (str_contains($value, '--')) {
            $fail('Slug must not contain consecutive hyphens.');
            return;
        }

        $reserved = self::reservedFirstSegments();
        if (in_array($value, $reserved, true)) {
            $fail("Slug '{$value}' is reserved by the router or the framework.");
        }
    }

    /**
     * Reserved first-segments. Reads the live route table when bootable;
     * falls back to FALLBACK_RESERVED in environments where the router
     * isn't usable (e.g. raw seeders called from CLI before bootstrap).
     *
     * Public for the codex:audit-slug-collisions command which reuses
     * the same list rather than maintaining a duplicate.
     */
    public static function reservedFirstSegments(): array
    {
        try {
            $routes = Route::getRoutes()->getRoutes();
            $segments = [];
            foreach ($routes as $route) {
                $uri = ltrim($route->uri(), '/');
                $first = strtok($uri, '/');
                if ($first !== false && ! str_contains($first, '{')) {
                    $segments[$first] = true;
                }
            }
            return array_values(array_unique(array_merge(
                array_keys($segments),
                self::FALLBACK_RESERVED,
            )));
        } catch (\Throwable) {
            return self::FALLBACK_RESERVED;
        }
    }
}
