<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Capability;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/search/index
 *
 * Single JSON document for client-side fuzzy search. Dashboard hydrates
 * a flexsearch / fuse.js index on first interaction. ~30KB gzipped at
 * Phase 1 size.
 *
 * Cached under codex:search:index — invalidated by the Filament
 * observer + CacheInvalidator on any catalogue write.
 *
 * Server-side full-text search via MySQL FULLTEXT is explicitly
 * out-of-scope per the plan: 50 caps × 90 projects fits comfortably
 * in a client-side index.
 */
class SearchIndexController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('codex:search:index', 3600, function () {
            return [
                'capabilities' => Capability::query()
                    ->whereNull('canonical_id')
                    ->orderBy('name')
                    ->get(['id', 'slug', 'name', 'category', 'description'])
                    ->map(fn (Capability $c) => [
                        'slug' => $c->slug,
                        'name' => $c->name,
                        'category' => $c->category,
                        'description_excerpt' => mb_substr(strip_tags($c->description), 0, 160),
                    ])
                    ->all(),
                'projects' => Project::query()
                    ->with(['capabilities:id,slug', 'technologies:id,slug'])
                    ->orderByDesc('id')
                    ->get([
                        'id', 'slug', 'name', 'project_type', 'short_description', 'client_industry',
                    ])
                    ->map(fn (Project $p) => [
                        'slug' => $p->slug,
                        'name' => $p->name,
                        'type' => $p->project_type,
                        'short_description' => $p->short_description,
                        'client_industry' => $p->client_industry,
                        'capability_slugs' => $p->capabilities->pluck('slug')->all(),
                        'technology_slugs' => $p->technologies->pluck('slug')->all(),
                    ])
                    ->all(),
            ];
        });

        return response()->json(['data' => $payload]);
    }
}
