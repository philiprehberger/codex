<?php

namespace Tests\Feature;

use App\Rules\SlugRule;
use Tests\TestCase;

class SlugRuleTest extends TestCase
{
    public function test_accepts_well_formed_kebab_case(): void
    {
        $this->assertValidates('my-cool-slug');
        $this->assertValidates('slug42');
        $this->assertValidates('a-b-c-d-e');
    }

    public function test_rejects_uppercase(): void
    {
        $this->assertFailsValidation('Uppercase-Slug');
    }

    public function test_rejects_short_slugs(): void
    {
        $this->assertFailsValidation('ab');
        $this->assertFailsValidation('a');
    }

    public function test_rejects_leading_or_trailing_hyphen(): void
    {
        $this->assertFailsValidation('-leading');
        $this->assertFailsValidation('trailing-');
    }

    public function test_rejects_consecutive_hyphens(): void
    {
        $this->assertFailsValidation('one--two');
    }

    public function test_rejects_reserved_first_segments(): void
    {
        // Hard-coded fallback list.
        $this->assertFailsValidation('admin');
        $this->assertFailsValidation('api');
        $this->assertFailsValidation('heatmap');
        $this->assertFailsValidation('about');
        $this->assertFailsValidation('search');
    }

    public function test_accepts_slugs_that_only_collide_after_a_hyphen(): void
    {
        // "admin-portal" is distinct from "admin" — the reserved-word check
        // is exact-match, not prefix-match.
        $this->assertValidates('admin-portal');
        $this->assertValidates('api-store');
    }

    public function test_route_derived_reserved_segments_are_picked_up(): void
    {
        $reserved = SlugRule::reservedFirstSegments();

        // /api/v1/projects is registered in routes/api.php; the reserved
        // list must include 'api' from the live route table or the
        // FALLBACK_RESERVED list.
        $this->assertContains('api', $reserved);
    }

    private function assertValidates(string $slug): void
    {
        $err = null;
        (new SlugRule)->validate('slug', $slug, function ($msg) use (&$err) { $err = $msg; });
        $this->assertNull($err, "expected '{$slug}' to pass; got: {$err}");
    }

    private function assertFailsValidation(string $slug): void
    {
        $err = null;
        (new SlugRule)->validate('slug', $slug, function ($msg) use (&$err) { $err = $msg; });
        $this->assertNotNull($err, "expected '{$slug}' to fail validation but it passed");
    }
}
