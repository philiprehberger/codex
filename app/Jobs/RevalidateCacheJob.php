<?php

namespace App\Jobs;

use App\Services\RevalidateClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued revalidation for bulk operations (> 10 tags). Admin response
 * returns immediately; the database queue worker (PM2 entry codex-queue
 * per Phase 8 §5) absorbs the HTTP latency.
 *
 * The codex-queue worker is non-optional in production — without it the
 * bulk-tag revalidation queue silently stalls and the cache never
 * invalidates.
 *
 * tries=3 + 5s backoff; the Phase 8 failed_jobs heartbeat alert catches
 * persistent failure (worker alive ≠ jobs succeeding).
 */
class RevalidateCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(public readonly array $tags) {}

    public function handle(RevalidateClient $client): void
    {
        foreach ($this->tags as $tag) {
            $client->post($tag);
        }
    }
}
