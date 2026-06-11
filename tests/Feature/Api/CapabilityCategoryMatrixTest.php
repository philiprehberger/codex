<?php

namespace Tests\Feature\Api;

use App\Models\Capability;
use App\Models\Industry;
use App\Models\Project;
use App\Services\CacheInvalidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 8.9 — capability-category × industry matrix endpoint.
 *
 *  - shape: categories[], industries[], cells[]
 *  - cell math: project count and capability count for the pair
 *  - alias rollup: a project tagged with an alias is attributed to the
 *    canonical's category, not the alias's
 *  - cache: codex:capability-matrix is hot on second hit
 *  - cache invalidator covers the new key
 */
class CapabilityCategoryMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_matrix_payload_is_the_documented_shape(): void
    {
        // Seed one row in each axis so the structure assertion has
        // something to walk — assertJsonStructure indexes into [0] and
        // would fail on empty arrays even when the shape is correct.
        $industry = Industry::factory()->create();
        $capability = Capability::factory()->create();
        $project = Project::factory()->create();
        $project->capabilities()->attach($capability->id);
        $project->industries()->attach($industry->id);

        $this->getJson('/api/v1/capabilities/category-matrix')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categories' => [['name', 'capability_count', 'project_count']],
                    'industries' => [['slug', 'name', 'project_count']],
                    'cells' => [['category', 'industry_slug', 'project_count', 'capability_count']],
                ],
            ]);
    }

    public function test_cell_counts_distinct_projects_and_capabilities(): void
    {
        // 1 industry, 2 capabilities in the same category, 2 projects
        // covering both — the cell should report (2 projects, 2
        // capabilities), not (2, 4) or (4, 2). Defends against the
        // missing-DISTINCT-on-aggregation regression.
        $industry = Industry::factory()->create();
        $cap1 = Capability::factory()->create(['category' => 'Commerce']);
        $cap2 = Capability::factory()->create(['category' => 'Commerce']);
        $p1 = Project::factory()->create();
        $p2 = Project::factory()->create();
        $p1->capabilities()->attach([$cap1->id, $cap2->id]);
        $p2->capabilities()->attach([$cap1->id, $cap2->id]);
        $p1->industries()->attach($industry->id);
        $p2->industries()->attach($industry->id);

        $this->getJson('/api/v1/capabilities/category-matrix')
            ->assertOk()
            ->assertJsonFragment([
                'category' => 'Commerce',
                'industry_slug' => $industry->slug,
                'project_count' => 2,
                'capability_count' => 2,
            ]);
    }

    public function test_alias_capability_rolls_up_to_canonical_category(): void
    {
        // Canonical "Auth" sits in UserMgmt; alias "user-auth" sits in
        // Marketing (a deliberate category mismatch — moderation should
        // ensure this never happens, but the controller must still
        // attribute to the canonical's category). The cell must land
        // under UserMgmt, not Marketing.
        $industry = Industry::factory()->create();
        $canonical = Capability::factory()->create(['slug' => 'auth', 'category' => 'UserMgmt']);
        $alias = Capability::factory()->create([
            'slug' => 'user-auth',
            'category' => 'Marketing',
            'canonical_id' => $canonical->id,
        ]);
        $project = Project::factory()->create();
        $project->capabilities()->attach($alias->id);
        $project->industries()->attach($industry->id);

        // assertJsonCount(1, 'data.cells') plus the UserMgmt fragment
        // implies no Marketing cell. assertJsonMissing on just the
        // category would over-match because the industries array shares
        // the slug — counting cells is the unambiguous probe here.
        $this->getJson('/api/v1/capabilities/category-matrix')
            ->assertOk()
            ->assertJsonCount(1, 'data.cells')
            ->assertJsonFragment([
                'category' => 'UserMgmt',
                'industry_slug' => $industry->slug,
                'project_count' => 1,
            ]);
    }

    public function test_serves_from_cache_when_populated(): void
    {
        $sentinel = [
            'categories' => [['name' => 'UserMgmt', 'capability_count' => 1, 'project_count' => 1]],
            'industries' => [['slug' => 'legal', 'name' => 'Legal', 'project_count' => 1]],
            'cells' => [['category' => 'UserMgmt', 'industry_slug' => 'legal', 'project_count' => 1, 'capability_count' => 1]],
        ];
        Cache::put('codex:capability-matrix', $sentinel, 3600);

        $this->assertSame(
            $sentinel,
            $this->getJson('/api/v1/capabilities/category-matrix')->assertOk()->json('data'),
        );
    }

    public function test_cache_invalidator_forgets_matrix_key(): void
    {
        $keys = app(CacheInvalidator::class)->reportKeys();

        $this->assertContains('codex:capability-matrix', $keys);
    }
}
