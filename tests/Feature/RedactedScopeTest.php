<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Scopes\RedactedScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 DoD (a): RedactedScope strips client_name + internal_notes on
 * default reads when visibility=redacted; withoutGlobalScope yields the
 * full row. internal_notes is also $hidden so toArray() never emits it,
 * regardless of scope state.
 */
class RedactedScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_redacted_scope_strips_client_name_and_internal_notes(): void
    {
        Project::factory()->redacted()->create([
            'slug' => 'scoped-test',
            'client_name' => 'Acme Corp',
            'client_industry' => 'Legal',
            'internal_notes' => 'sensitive notes',
        ]);

        $scoped = Project::where('slug', 'scoped-test')->firstOrFail();

        $this->assertNull($scoped->client_name);
        $this->assertSame('Legal', $scoped->client_industry); // industry stays — that's the proof-of-portfolio shape
        $this->assertNull($scoped->internal_notes);
    }

    public function test_withoutGlobalScope_yields_full_row(): void
    {
        Project::factory()->redacted()->create([
            'slug' => 'unscoped-test',
            'client_name' => 'Acme Corp',
            'internal_notes' => 'sensitive notes',
        ]);

        $unscoped = Project::withoutGlobalScope(RedactedScope::class)
            ->where('slug', 'unscoped-test')
            ->firstOrFail();

        $this->assertSame('Acme Corp', $unscoped->client_name);
        $this->assertSame('sensitive notes', $unscoped->internal_notes);
    }

    public function test_internal_notes_is_hidden_from_toArray_regardless_of_scope(): void
    {
        Project::factory()->redacted()->create([
            'slug' => 'hidden-test',
            'client_name' => 'Acme Corp',
            'internal_notes' => 'sensitive notes',
        ]);

        $scoped = Project::where('slug', 'hidden-test')->firstOrFail();
        $unscoped = Project::withoutGlobalScope(RedactedScope::class)
            ->where('slug', 'hidden-test')
            ->firstOrFail();

        $this->assertArrayNotHasKey('internal_notes', $scoped->toArray());
        $this->assertArrayNotHasKey('internal_notes', $unscoped->toArray());
    }

    public function test_public_project_emits_client_name_when_set(): void
    {
        Project::factory()->create([
            'slug' => 'public-with-client',
            'visibility' => 'public',
            'client_name' => 'Public Co',
        ]);

        $project = Project::where('slug', 'public-with-client')->firstOrFail();

        $this->assertSame('Public Co', $project->client_name);
    }
}
