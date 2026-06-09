<?php

namespace App\Models;

/**
 * Result of Capability::canBeMergedInto() — used by the Filament
 * action and the MergeCapability service to decide whether to proceed
 * and what message to surface on rejection.
 */
final readonly class MergeEligibility
{
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
    ) {}

    public static function allowed(): self
    {
        return new self(allowed: true);
    }

    public static function rejected(string $reason): self
    {
        return new self(allowed: false, reason: $reason);
    }
}
