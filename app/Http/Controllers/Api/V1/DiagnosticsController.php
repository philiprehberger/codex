<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Codex-extended health for BetterStack monitors. Per Phase 8 §10:
 * worker-alive ≠ jobs-succeeding, so `failed_jobs` count is the second
 * layer the alert routes off.
 *
 * Bypasses throttle:codex.api (registered without the throttle middleware
 * in routes/api.php) so BetterStack's 60s ping × 2 subdomains doesn't
 * eat the rate-limit budget.
 */
class DiagnosticsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'status' => 'ok',
            'db' => $this->dbHealthy() ? 'ok' : 'fail',
            'cache' => $this->cacheHealthy() ? 'ok' : 'fail',
            'queue' => $this->queueDriverHealthy() ? 'ok' : 'fail',
            'failed_jobs' => $this->failedJobsLastHour(),
        ];

        $unhealthy = $checks['db'] === 'fail'
            || $checks['cache'] === 'fail'
            || $checks['queue'] === 'fail';
        if ($unhealthy) {
            $checks['status'] = 'fail';
        }

        return response()
            ->json($checks, $unhealthy ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }

    private function dbHealthy(): bool
    {
        try {
            DB::select('SELECT 1 AS ok');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function cacheHealthy(): bool
    {
        try {
            Cache::put('codex:diag:probe', 'ok', 5);
            return Cache::get('codex:diag:probe') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function queueDriverHealthy(): bool
    {
        try {
            // Database driver is the production target; sync is dev-only.
            // For both, verifying the connection resolves is enough.
            app('queue')->connection();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function failedJobsLastHour(): int
    {
        try {
            return DB::table('failed_jobs')
                ->where('failed_at', '>', now()->subHour())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
