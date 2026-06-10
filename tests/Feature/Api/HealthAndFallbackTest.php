<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 5 DoD: health endpoints + unversioned API fallback.
 *  - /up returns 200 empty body when healthy (Laravel convention)
 *  - /up/diagnostics returns JSON with the documented shape
 *  - /up/queue returns 503 with no heartbeat, 200 with fresh heartbeat
 *  - /api/projects (no v1) returns 410 with RFC 7807 body pointing at /api/v1/
 */
class HealthAndFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_returns_200_when_healthy(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_diagnostics_returns_json_with_documented_keys(): void
    {
        $response = $this->getJson('/up/diagnostics')->assertOk();
        $response->assertJsonStructure(['status', 'db', 'cache', 'queue', 'failed_jobs']);
        $this->assertSame('ok', $response->json('status'));
    }

    public function test_queue_heartbeat_returns_503_when_missing(): void
    {
        Cache::forget('codex:queue:heartbeat');
        $this->get('/up/queue')->assertStatus(503);
    }

    public function test_queue_heartbeat_returns_200_with_fresh_heartbeat(): void
    {
        Cache::put('codex:queue:heartbeat', now()->toIso8601String(), 600);
        $this->get('/up/queue')->assertOk();
    }

    public function test_queue_heartbeat_returns_503_with_stale_heartbeat(): void
    {
        Cache::put('codex:queue:heartbeat', now()->subMinutes(10)->toIso8601String(), 1800);
        $this->get('/up/queue')->assertStatus(503);
    }

    public function test_unversioned_api_returns_410_with_problem_detail(): void
    {
        $response = $this->getJson('/api/projects');
        $response->assertStatus(410);
        $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance']);
        $this->assertSame('Gone', $response->json('title'));
        $this->assertSame('/api/v1/', $response->json('instance'));
    }

    public function test_resume_bullets_returns_200_with_bullets(): void
    {
        $response = $this->getJson('/api/v1/reports/resume-bullets')->assertOk();
        $response->assertJsonStructure([
            'data' => ['by_capability', 'by_industry', 'by_architecture'],
        ]);
    }

    public function test_search_index_returns_capabilities_and_projects(): void
    {
        $response = $this->getJson('/api/v1/search/index')->assertOk();
        $response->assertJsonStructure([
            'data' => ['capabilities', 'projects'],
        ]);
    }

    public function test_gap_report_returns_capability_gaps_and_coverage(): void
    {
        $response = $this->getJson('/api/v1/reports/gaps')->assertOk();
        $response->assertJsonStructure([
            'data' => ['capability_gaps', 'tech_industry_coverage'],
        ]);
    }
}
