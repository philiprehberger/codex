<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Centralises the named-key Cache::forget() fan-out for the report
 * endpoints. Cache::tags() doesn't work on the file or database driver
 * (per the plan's revised Phase 5 caching spec) — so we maintain an
 * explicit list of keys in config/codex.php and forget all of them
 * on any catalogue write.
 *
 * Adding a fifth cached endpoint: append the key to
 * config/codex.php → cache.report_keys. This helper picks it up
 * automatically and the Phase 7 test asserts the helper covers every
 * configured key.
 */
class CacheInvalidator
{
    /**
     * Forget every cached report key. Returns the count of keys
     * processed so tests can assert coverage.
     */
    public function forgetReports(): int
    {
        $keys = (array) Config::get('codex.cache.report_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        return count($keys);
    }

    /** @return array<int, string> */
    public function reportKeys(): array
    {
        return array_values((array) Config::get('codex.cache.report_keys', []));
    }
}
