<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/capabilities
 *
 * Returns canonical capabilities (canonical_id IS NULL) with their
 * project + package counts. Aliases are excluded — their counts roll
 * up into the canonical via COALESCE(canonical_id, id) in both join
 * conditions.
 *
 * Soft-deleted rows are filtered via deleted_at IS NULL on the joined
 * tables (see plan §"Soft-delete + cascade interaction") — pivot rows
 * survive soft-delete and would otherwise inflate counts.
 *
 * Project + package counts are computed as separate aggregates rather
 * than a single join chain to avoid the Cartesian blow-up that COUNT
 * DISTINCT would otherwise paper over at scale.
 */
class ListCapabilitiesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $projectCounts = DB::table('project_capabilities AS pc')
            ->join('projects AS p', function ($join) {
                $join->on('p.id', '=', 'pc.project_id')->whereNull('p.deleted_at');
            })
            ->select('pc.capability_id', DB::raw('COUNT(DISTINCT p.id) AS n'))
            ->groupBy('pc.capability_id');

        $packageCounts = DB::table('package_capabilities AS pkc')
            ->join('packages AS pk', function ($join) {
                $join->on('pk.id', '=', 'pkc.package_id')->whereNull('pk.deleted_at');
            })
            ->select('pkc.capability_id', DB::raw('COUNT(DISTINCT pk.id) AS n'))
            ->groupBy('pkc.capability_id');

        $rows = DB::table('capabilities AS c')
            ->leftJoinSub($projectCounts, 'proj', fn ($join) => $join->on('proj.capability_id', '=', DB::raw('COALESCE(c.canonical_id, c.id)')))
            ->leftJoinSub($packageCounts, 'pkg', fn ($join) => $join->on('pkg.capability_id', '=', DB::raw('COALESCE(c.canonical_id, c.id)')))
            ->whereNull('c.canonical_id')
            ->orderBy('c.category')
            ->orderBy('c.name')
            ->get([
                'c.id', 'c.slug', 'c.name', 'c.category', 'c.description', 'c.icon',
                DB::raw('COALESCE(proj.n, 0) AS project_count'),
                DB::raw('COALESCE(pkg.n, 0) AS package_count'),
            ]);

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'slug' => $r->slug,
                'name' => $r->name,
                'category' => $r->category,
                'description' => $r->description,
                'icon' => $r->icon,
                'project_count' => (int) $r->project_count,
                'package_count' => (int) $r->package_count,
            ])->all(),
        ]);
    }
}
