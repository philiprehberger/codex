<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/capabilities/heatmap
 *
 * Sparse payload per the plan's data-shape spec:
 *   capabilities: [{id, slug, name, count}]
 *   projects:     [{id, slug, name, type}]
 *   cells:        [{capability_id, project_id, is_primary}]
 *
 * At 45 caps × 71 projects × ~6 cells per project, that's ~430 cells —
 * ~30KB gzipped vs ~150KB for a dense matrix. The Next.js renderer
 * materialises the dense view client-side.
 *
 * Aggregation joins through COALESCE(canonical_id, id) so merged
 * aliases roll up into their terminal canonical. Soft-deleted projects
 * are filtered out (deleted_at IS NULL) — the plan's load-bearing
 * soft-delete + cascade discipline.
 *
 * Cached under codex:heatmap with the database driver (Cache::tags()
 * doesn't work on database driver per the revised Phase 5 caching spec).
 */
class HeatmapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('codex:heatmap', 3600, function () {
            return $this->build();
        });

        return response()->json(['data' => $payload]);
    }

    private function build(): array
    {
        // Capabilities: only canonicals (canonical_id IS NULL) appear as
        // heatmap rows. Aliased rows roll up into their terminal canonical.
        $capabilities = DB::table('capabilities')
            ->whereNull('canonical_id')
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'category'])
            ->keyBy('id');

        // Projects: non-deleted, ordered by id desc (newest first).
        $projects = DB::table('projects')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get(['id', 'slug', 'name', 'project_type'])
            ->keyBy('id');

        // Cells: pivot rows joined back through canonical resolution.
        // The pivot's capability_id may point at an alias; we re-key
        // to the canonical so the cell lands in the canonical row.
        $cells = DB::table('project_capabilities AS pc')
            ->join('capabilities AS c', 'c.id', '=', 'pc.capability_id')
            ->join('projects AS p', function ($join) {
                $join->on('p.id', '=', 'pc.project_id')->whereNull('p.deleted_at');
            })
            ->select([
                DB::raw('COALESCE(c.canonical_id, c.id) AS canonical_capability_id'),
                'p.id AS project_id',
                'pc.is_primary',
            ])
            ->get();

        // Per-capability totals (the count in the heatmap row).
        $countsByCanonical = $cells
            ->groupBy('canonical_capability_id')
            ->map(fn ($group) => $group->pluck('project_id')->unique()->count());

        return [
            'capabilities' => $capabilities->values()->map(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'category' => $c->category,
                'count' => (int) ($countsByCanonical[$c->id] ?? 0),
            ])->all(),
            'projects' => $projects->values()->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'type' => $p->project_type,
            ])->all(),
            'cells' => $cells->map(fn ($cell) => [
                'capability_id' => $cell->canonical_capability_id,
                'project_id' => $cell->project_id,
                'is_primary' => (bool) $cell->is_primary,
            ])->values()->all(),
        ];
    }
}
