<?php

namespace Tests\Feature\Api;

use App\Models\Capability;
use App\Models\Industry;
use App\Models\Project;
use App\Models\Technology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 DoD: GET /api/v1/projects.
 *  - happy path returns 200 + paginated data
 *  - RedactedScope strips client_name on redacted rows; client_industry stays
 *  - unknown filter parameter returns 422 with RFC 7807 body
 *  - filter shape is validated (rejects SQL-shaped inputs)
 *  - cursor pagination round-trips
 */
class ListProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_200_with_paginated_data(): void
    {
        Project::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/projects');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'slug', 'name', 'project_type', 'status', 'visibility', 'capabilities', 'technologies']],
                'meta' => ['next_cursor', 'prev_cursor', 'per_page'],
            ]);
    }

    public function test_redacted_scope_strips_client_name_but_keeps_client_industry(): void
    {
        Project::factory()->redacted()->create([
            'slug' => 'redacted-client',
            'client_name' => 'Acme Corp',
            'client_industry' => 'legal',
        ]);

        $response = $this->getJson('/api/v1/projects?per_page=100');
        $data = collect($response->json('data'))->firstWhere('slug', 'redacted-client');

        $this->assertNotNull($data);
        $this->assertNull($data['client_name'], 'client_name must be stripped on redacted rows');
        $this->assertSame('legal', $data['client_industry'], 'client_industry stays visible — that is the proof-of-portfolio shape');
    }

    public function test_unknown_filter_parameter_returns_422_problem_detail(): void
    {
        $response = $this->getJson('/api/v1/projects?bogus_filter=anything');

        $response->assertStatus(422)
            ->assertJsonStructure(['type', 'title', 'status']);
    }

    public function test_sql_injection_shaped_input_is_rejected(): void
    {
        $this->getJson('/api/v1/projects?capability=DROP TABLE projects')
            ->assertStatus(422);

        $this->getJson('/api/v1/projects?industry=DROP TABLE projects')
            ->assertStatus(422);
    }

    public function test_filter_by_capability_returns_only_matching_projects(): void
    {
        $cap = Capability::factory()->create(['slug' => 'auth']);
        $p1 = Project::factory()->create(['slug' => 'has-auth']);
        $p1->capabilities()->attach($cap->id);
        $p2 = Project::factory()->create(['slug' => 'no-auth']);

        $response = $this->getJson('/api/v1/projects?capability=auth&per_page=100');
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains('has-auth', $slugs);
        $this->assertNotContains('no-auth', $slugs);
    }

    public function test_cursor_pagination_round_trips(): void
    {
        Project::factory()->count(6)->create();

        $page1 = $this->getJson('/api/v1/projects?per_page=2')->assertOk();
        $nextCursor = $page1->json('meta.next_cursor');
        $this->assertNotNull($nextCursor);

        $page2 = $this->getJson('/api/v1/projects?per_page=2&cursor='.$nextCursor)->assertOk();
        $this->assertCount(2, $page2->json('data'));

        // Pages don't overlap.
        $page1Slugs = collect($page1->json('data'))->pluck('slug')->all();
        $page2Slugs = collect($page2->json('data'))->pluck('slug')->all();
        $this->assertEmpty(array_intersect($page1Slugs, $page2Slugs));
    }
}
