<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * 200 if the codex-queue PM2 worker has heartbeat'd within the last 5
 * minutes; 503 otherwise. BetterStack polls this every 60s — sustained
 * 503 is the worker-crashed-and-PM2-didn't-restart-it signal.
 *
 * The queue worker writes the heartbeat from inside its queue:work loop
 * (Laravel\'s Queue::looping event listener — wired in a future
 * EventServiceProvider when Phase 8 brings up the worker for real).
 */
class QueueHeartbeatController extends Controller
{
    public const HEARTBEAT_KEY = 'codex:queue:heartbeat';
    public const HEARTBEAT_TTL_SECONDS = 300; // 5 minutes

    public function __invoke(): Response
    {
        $last = Cache::get(self::HEARTBEAT_KEY);
        if (! $last) {
            return response('queue: no heartbeat', 503)
                ->header('Cache-Control', 'no-store');
        }

        // Stored as ISO-8601 string for cross-driver compatibility.
        try {
            $lastAt = \Carbon\Carbon::parse($last);
        } catch (\Throwable) {
            return response('queue: heartbeat unparseable', 503);
        }

        $ageSeconds = $lastAt->diffInSeconds(now());
        if ($ageSeconds > self::HEARTBEAT_TTL_SECONDS) {
            return response("queue: stale heartbeat ({$ageSeconds}s old)", 503)
                ->header('Cache-Control', 'no-store');
        }

        return response('queue: ok', 200)->header('Cache-Control', 'no-store');
    }
}
