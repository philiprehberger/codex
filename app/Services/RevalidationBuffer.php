<?php

namespace App\Services;

/**
 * Per-request collection of cache tags to revalidate against the Next.js
 * dashboard. Singleton-scoped via the container (registered in
 * AppServiceProvider::register as ->scoped()), so:
 *  - one request → one buffer
 *  - bulk-tag action across 30 projects → 30 buffered tags but ONE flush
 *  - downstream actions/observers add tags without knowing about each
 *    other; the terminating() callback fires once at the end of the
 *    request
 *
 * Flush mechanics live in RevalidateClient (HTTP path) and the
 * RevalidateCacheJob (queue path).
 */
class RevalidationBuffer
{
    /** @var array<string, true> */
    private array $tags = [];

    public function add(string $tag): void
    {
        $this->tags[$tag] = true;
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return array_keys($this->tags);
    }

    public function isEmpty(): bool
    {
        return $this->tags === [];
    }

    public function clear(): void
    {
        $this->tags = [];
    }

    public function count(): int
    {
        return count($this->tags);
    }
}
