<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Capability;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/capabilities/{slug}
 *
 * If the slug resolves to an alias, the response follows to the terminal
 * canonical and includes a `redirected_from` field so the dashboard /
 * SDK can detect the chain hop. Project list returns projects tagged
 * with the resolved canonical (the alias\'s own pivots reference the
 * alias_id at the schema level but resolve via COALESCE in reads).
 */
class ShowCapabilityController extends Controller
{
    public function __invoke(string $slug): JsonResponse
    {
        $capability = Capability::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $canonical = $capability->resolveCanonical();

        // Projects tagged with the canonical, plus projects tagged with
        // any alias that resolves to it.
        $aliasIds = Capability::query()
            ->where('canonical_id', $canonical->id)
            ->pluck('id')
            ->push($canonical->id)
            ->all();

        $projects = Project::query()
            ->whereHas('capabilities', fn ($q) => $q->whereIn('capabilities.id', $aliasIds))
            ->orderByDesc('id')
            ->get([
                'id', 'slug', 'name', 'project_type', 'status', 'visibility',
                'short_description', 'client_industry', 'shipped_date',
            ]);

        return response()->json([
            'data' => [
                'id' => $canonical->id,
                'slug' => $canonical->slug,
                'name' => $canonical->name,
                'category' => $canonical->category,
                'description' => $canonical->description,
                'icon' => $canonical->icon,
                'redirected_from' => $capability->id !== $canonical->id ? $capability->slug : null,
                'aliases' => Capability::query()
                    ->where('canonical_id', $canonical->id)
                    ->orderBy('name')
                    ->pluck('slug')
                    ->all(),
                'projects' => $projects->map(fn ($p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'project_type' => $p->project_type,
                    'status' => $p->status,
                    'visibility' => $p->visibility,
                    'short_description' => $p->short_description,
                    'client_industry' => $p->client_industry,
                    'shipped_date' => $p->shipped_date?->toDateString(),
                ])->all(),
                'project_count' => $projects->count(),
            ],
        ]);
    }
}
