<?php

namespace App\Observers;

use App\Services\RevalidationBuffer;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic observer registered against Project, Capability, and every
 * pivot model (the seven CodexPivot subclasses). On saved/deleted,
 * buffers the cache tags this row touches. The actual HTTP fan-out
 * happens once at request terminating() time via the buffer flush in
 * AppServiceProvider.
 *
 * Tag strategy is intentionally coarse — 4 named cache keys (heatmap,
 * gaps, bullets, search:index) cover every public surface and the
 * Filament observer just forgets/invalidates all 4 on any catalogue
 * write. Cache::tags() doesn't work on the database driver (per the
 * plan's revised Phase 5 spec); explicit named keys with explicit
 * invalidation is the load-bearing replacement.
 *
 * The same tags also feed the Next.js side's revalidateTag() calls so
 * the dashboard SSR cache invalidates on the same write.
 */
class RevalidationObserver
{
    /** @var array<int, string> */
    private const TAGS = [
        'codex:heatmap',
        'codex:reports:gaps',
        'codex:reports:bullets',
        'codex:search:index',
    ];

    public function __construct(private readonly RevalidationBuffer $buffer) {}

    public function saved(Model $model): void
    {
        $this->buffer();
    }

    public function deleted(Model $model): void
    {
        $this->buffer();
    }

    public function restored(Model $model): void
    {
        $this->buffer();
    }

    public function forceDeleted(Model $model): void
    {
        $this->buffer();
    }

    private function buffer(): void
    {
        foreach (self::TAGS as $tag) {
            $this->buffer->add($tag);
        }
    }
}
