<?php

namespace App\Providers;

use App\Jobs\RevalidateCacheJob;
use App\Models\Capability;
use App\Services\CacheInvalidator;
use App\Models\Pivots\ProjectArchitecturePivot;
use App\Models\Pivots\ProjectCapabilityPivot;
use App\Models\Pivots\ProjectDeliverablePivot;
use App\Models\Pivots\ProjectDesignStylePivot;
use App\Models\Pivots\ProjectIndustryPivot;
use App\Models\Pivots\ProjectTagMapPivot;
use App\Models\Pivots\ProjectTechnologyPivot;
use App\Models\Project;
use App\Observers\RevalidationObserver;
use App\Services\RevalidateClient;
use App\Services\RevalidationBuffer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Per-request singleton — buffers cache-revalidation tags so a
        // bulk-tag action across N projects fires one HTTP fan-out, not
        // N. The scoped() binding wins fresh in each request lifecycle.
        $this->app->scoped(RevalidationBuffer::class);
        $this->app->singleton(RevalidateClient::class);
        $this->app->singleton(CacheInvalidator::class);
    }

    public function boot(): void
    {
        // Default password complexity for the admin profile form, the
        // Reset-Admin-Password command, and any future tweak. Mirrors
        // the plan's Phase 3 requirement: min 16, mixed case, numbers,
        // symbols, not in haveibeenpwned.
        Password::defaults(function () {
            return App::isProduction()
                ? Password::min(16)->mixedCase()->numbers()->symbols()->uncompromised()
                : Password::min(12)->mixedCase()->numbers();
        });

        // 5/min/IP throttle on admin login (per plan Phase 3 §"Auth").
        // Layered on top of Laravel's defaults — the named limiter is
        // applied at the panel's login route.
        RateLimiter::for('codex.admin-login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(10)->by(strtolower($request->input('email').'|'.$request->ip())),
            ];
        });

        // Public read-API throttles. Per plan §"Rate limiting": all
        // /api/v1/* under codex.api (60/min/ip); heavy aggregations
        // (/heatmap + /reports/*) under codex.api-heavy (20/min/ip).
        RateLimiter::for('codex.api', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
        RateLimiter::for('codex.api-heavy', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));

        $this->registerRevalidationObservers();
        $this->registerRevalidationFlush();
    }

    /**
     * Register the observer against Project, Capability, and every pivot
     * model. The CodexPivot ->using() wiring on the relations means pivot
     * model events fire on attach() / sync() / updateExistingPivot.
     */
    private function registerRevalidationObservers(): void
    {
        $observed = [
            Project::class,
            Capability::class,
            ProjectCapabilityPivot::class,
            ProjectTechnologyPivot::class,
            ProjectIndustryPivot::class,
            ProjectArchitecturePivot::class,
            ProjectDeliverablePivot::class,
            ProjectDesignStylePivot::class,
            ProjectTagMapPivot::class,
        ];

        foreach ($observed as $class) {
            $class::observe(RevalidationObserver::class);
        }
    }

    /**
     * Hook the terminating() lifecycle so the buffered tags fire after
     * the Filament response is flushed. Bulk operations (>10 tags) push
     * to the database queue; smaller ones POST inline. Either way, the
     * buffer is cleared so a single request never double-fires.
     */
    private function registerRevalidationFlush(): void
    {
        $this->app->terminating(function () {
            /** @var RevalidationBuffer $buffer */
            $buffer = app(RevalidationBuffer::class);
            if ($buffer->isEmpty()) {
                return;
            }

            $tags = $buffer->tags();
            $buffer->clear();

            // Local Laravel-side cache forget first — the dashboard's
            // Next.js cache invalidation is best-effort (downed Next.js
            // is non-blocking), but our own cache must be authoritative
            // immediately on admin write.
            app(CacheInvalidator::class)->forgetReports();

            // > 10 tags is the bulk threshold. In practice with our
            // 4-tag coarse set, this only trips on multi-record bulk
            // ops — single-row admin writes always go inline.
            $threshold = (int) (config('codex.revalidate.queue_threshold') ?? 10);
            if (count($tags) > $threshold) {
                RevalidateCacheJob::dispatch($tags);
                return;
            }

            /** @var \App\Services\RevalidateClient $client */
            $client = app(RevalidateClient::class);
            foreach ($tags as $tag) {
                $client->post($tag);
            }
        });
    }
}
