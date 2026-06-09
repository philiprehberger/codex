<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2 DoD (b): a Project with status=shipped requires both
 * shipped_date and hours_actual. The saving observer raises a
 * ValidationException — so Tinker, seeders, and direct Eloquent
 * writes all hit the same wall as Filament's form validators.
 */
class ShippedInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipped_without_shipped_date_raises_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        Project::factory()->shipped()->create([
            'shipped_date' => null,
        ]);
    }

    public function test_shipped_without_hours_actual_raises_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        Project::factory()->shipped()->create([
            'hours_actual' => null,
        ]);
    }

    public function test_shipped_with_both_fields_persists(): void
    {
        $project = Project::factory()->shipped()->create([
            'shipped_date' => '2026-06-09',
            'hours_actual' => 42,
        ]);

        $this->assertSame('shipped', $project->fresh()->status);
        $this->assertSame('2026-06-09', $project->fresh()->shipped_date->toDateString());
        $this->assertSame(42, $project->fresh()->hours_actual);
    }

    public function test_active_project_does_not_require_shipped_fields(): void
    {
        $project = Project::factory()->create([
            'status' => 'active',
            'shipped_date' => null,
            'hours_actual' => null,
        ]);

        $this->assertSame('active', $project->fresh()->status);
    }

    public function test_idea_project_does_not_require_shipped_fields(): void
    {
        $project = Project::factory()->idea()->create([
            'shipped_date' => null,
            'hours_actual' => null,
        ]);

        $this->assertSame('idea', $project->fresh()->status);
    }
}
