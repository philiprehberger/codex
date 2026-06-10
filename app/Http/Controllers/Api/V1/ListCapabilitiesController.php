<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/capabilities
 *
 * Returns canonical capabilities (canonical_id IS NULL) with their
 * project counts. Aliases are excluded — their counts roll up into the
 * canonical via COALESCE(canonical_id, id) in the aggregation join.
 *
 * Soft-deleted projects are filtered out via the inner join's
 * deleted_at IS NULL condition (see plan §"Soft-delete + cascade
 * interaction"). Without this, a soft-deleted project's pivot rows
 * survive (CASCADE doesn't fire on soft-delete) and inflate counts.
 */
class ListCapabilitiesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $rows = DB::table('capabilities AS c')
            ->leftJoin('project_capabilities AS pc',
                fn ($join) => $join->on(DB::raw('COALESCE(c.canonical_id, c.id)'), '=', 'pc.capability_id'),
            )
            ->leftJoin('projects AS p', function ($join) {
                $join->on('p.id', '=', 'pc.project_id')->whereNull('p.deleted_at');
            })
            ->whereNull('c.canonical_id')
            ->groupBy('c.id', 'c.slug', 'c.name', 'c.category', 'c.description', 'c.icon')
            ->orderBy('c.category')
            ->orderBy('c.name')
            ->get([
                'c.id', 'c.slug', 'c.name', 'c.category', 'c.description', 'c.icon',
                DB::raw('COUNT(DISTINCT p.id) AS project_count'),
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
            ])->all(),
        ]);
    }
}
