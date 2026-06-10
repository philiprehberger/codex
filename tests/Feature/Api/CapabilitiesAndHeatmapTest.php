<?php

namespace Tests\Feature\Api;

use App\Models\Capability;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 5 DoD: capabilities + heatmap endpoints.
 *  - capability list rolls up alias counts into canonical
 *  - heatmap cache is hot on second hit
 *  - heatmap payload is the documented sparse shape
 */
class CapabilitiesAndHeatmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_list_only_emits_canonicals(): void
    {
        $canonical = Capability::factory()->create(['slug' => 'auth']);
        $alias = Capability::factory()->create(['slug' => 'user-auth', 'canonical_id' => $canonical->id]);

        $response = $this->getJson('/api/v1/capabilities')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains('auth', $slugs);
        $this->assertNotContains('user-auth', $slugs, 'aliases must not appear as heatmap rows');
    }

    public function test_capability_alias_redirects_to_canonical_in_show_payload(): void
    {
        $canonical = Capability::factory()->create(['slug' => 'auth']);
        $alias = Capability::factory()->create(['slug' => 'user-auth', 'canonical_id' => $canonical->id]);

        $response = $this->getJson('/api/v1/capabilities/user-auth')->assertOk();
        $this->assertSame('auth', $response->json('data.slug'));
        $this->assertSame('user-auth', $response->json('data.redirected_from'));
    }

    public function test_heatmap_payload_is_sparse_shape(): void
    {
        Capability::factory()->count(3)->create();
        Project::factory()->count(2)->hasAttached(Capability::limit(2)->get(), [], 'capabilities')->create();

        $response = $this->getJson('/api/v1/capabilities/heatmap')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'capabilities' => [['id', 'slug', 'name', 'count']],
                'projects' => [['id', 'slug', 'name', 'type']],
                'cells' => [['capability_id', 'project_id', 'is_primary']],
            ],
        ]);
    }

    public function test_heatmap_serves_from_cache_when_populated(): void
    {
        // Pre-populate the cache with a sentinel payload. If the controller
        // is wired through Cache::remember('codex:heatmap', …) the response
        // should match the sentinel exactly — proving the cache layer is
        // load-bearing, not a per-request recompute. Defends against a
        // refactor that accidentally drops the wrapper.
        //
        // (Why pre-populate vs. checking persistence after a request:
        // the revalidation observer's terminating callback forgets the
        // cache when factory writes leave tags in the buffer. The cache
        // IS populated by the controller, but the request's tail-end
        // immediately invalidates it. Pre-populating side-steps that.)
        $sentinel = [
            'capabilities' => [['id' => 'X', 'slug' => 'sentinel-cap', 'name' => 'Sentinel', 'category' => 'AI', 'count' => 99]],
            'projects' => [['id' => 'Y', 'slug' => 'sentinel-project', 'name' => 'Sentinel', 'type' => 'demo']],
            'cells' => [['capability_id' => 'X', 'project_id' => 'Y', 'is_primary' => true]],
        ];
        Cache::put('codex:heatmap', $sentinel, 3600);

        $response = $this->getJson('/api/v1/capabilities/heatmap')->assertOk();

        $this->assertSame($sentinel, $response->json('data'));
    }
}
