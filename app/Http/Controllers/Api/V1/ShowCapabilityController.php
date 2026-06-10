<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Capability;
use App\Models\Package;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/capabilities/{slug}
 *
 * If the slug resolves to an alias, the response follows to the terminal
 * canonical and includes a `redirected_from` field so the dashboard /
 * SDK can detect the chain hop. Project list returns projects tagged
 * with the resolved canonical (the alias's own pivots reference the
 * alias_id at the schema level but resolve via COALESCE in reads).
 *
 * Packages list (Phase 8.3): same canonical-resolution behaviour. Returns
 * package primary keys + name/language/registry/description for the
 * dashboard's capability page to list them alongside projects.
 */
class ShowCapabilityController extends Controller
{
    public function __invoke(string $slug): JsonResponse
    {
        $capability = Capability::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $canonical = $capability->resolveCanonical();

        // Projects + packages tagged with the canonical, plus any tagged
        // with an alias that resolves to it.
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

        $packages = Package::query()
            ->whereHas('capabilities', fn ($q) => $q->whereIn('capabilities.id', $aliasIds))
            ->with(['capabilities' => fn ($q) => $q->whereIn('capabilities.id', $aliasIds)])
            ->orderBy('language')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'language', 'registry', 'short_description', 'repo_url']);

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
                'projects' => $projects->map(fn (Project $p) => [
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
                'packages' => $packages->map(function (Package $pkg) {
                    $cap = $pkg->capabilities->first();

                    return [
                        'slug' => $pkg->slug,
                        'name' => $pkg->name,
                        'language' => $pkg->language,
                        'registry' => $pkg->registry,
                        'short_description' => $pkg->short_description,
                        'repo_url' => $pkg->repo_url,
                        'is_primary' => $cap !== null && (bool) $cap->pivot->is_primary,
                    ];
                })->all(),
                'package_count' => $packages->count(),
            ],
        ]);
    }
}
