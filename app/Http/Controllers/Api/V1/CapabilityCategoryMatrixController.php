<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/capabilities/category-matrix
 *
 * Capability-category × industry matrix — the headline view at /heatmap.
 * Where the sparse 68×47 project matrix reads as "mostly empty," this
 * 9×~10 grid groups capabilities into their curated categories (User
 * Mgmt, Commerce, Marketing, …) and crosses them with industries. Every
 * cell carries a project count and a capability count, so intensity is
 * meaningful rather than binary.
 *
 * Shape:
 *   categories: [{name, capability_count, project_count}]
 *   industries: [{slug, name, project_count}]
 *   cells:      [{category, industry_slug, project_count, capability_count}]
 *
 * Aliases roll up via COALESCE(canonical_id, id) and inherit the
 * canonical's category — so merging "user-auth" into "auth" doesn't
 * leave an orphan category attribution. Soft-deleted projects are
 * filtered.
 *
 * Cached under codex:capability-matrix; invalidated by the Filament
 * observer via the CacheInvalidator key list.
 */
class CapabilityCategoryMatrixController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('codex:capability-matrix', 3600, function () {
            return $this->build();
        });

        return response()->json(['data' => $payload]);
    }

    /**
     * @return array{
     *     categories: array<int, array{name: string, capability_count: int, project_count: int}>,
     *     industries: array<int, array{slug: string, name: string, project_count: int}>,
     *     cells: array<int, array{category: string, industry_slug: string, project_count: int, capability_count: int}>
     * }
     */
    private function build(): array
    {
        // Per-cell aggregation. Join order:
        //   project_capabilities → projects (drop soft-deleted)
        //                       → capabilities (so we can resolve canonical
        //                         and its category)
        //                       → project_industries → industries
        // The category we attribute to comes from the canonical row when
        // the tagged capability is an alias — so a project tagged with
        // alias "user-auth" rolls up under canonical "auth"'s category.
        $cells = DB::table('project_capabilities AS pc')
            ->join('projects AS p', function ($j) {
                $j->on('p.id', '=', 'pc.project_id')->whereNull('p.deleted_at');
            })
            ->join('capabilities AS c', 'c.id', '=', 'pc.capability_id')
            ->leftJoin('capabilities AS canonical_c', 'canonical_c.id', '=', 'c.canonical_id')
            ->join('project_industries AS pi', 'pi.project_id', '=', 'p.id')
            ->join('industries AS i', 'i.id', '=', 'pi.industry_id')
            ->select([
                DB::raw('COALESCE(canonical_c.category, c.category) AS category'),
                'i.slug AS industry_slug',
                'i.name AS industry_name',
                DB::raw('COUNT(DISTINCT p.id) AS project_count'),
                DB::raw('COUNT(DISTINCT COALESCE(c.canonical_id, c.id)) AS capability_count'),
            ])
            ->groupBy(DB::raw('COALESCE(canonical_c.category, c.category)'), 'i.slug', 'i.name')
            ->get();

        // Per-category totals — distinct canonical capabilities and
        // distinct projects covered by any capability in that category.
        $categoryTotals = DB::table('capabilities AS c')
            ->leftJoin('capabilities AS canonical_c', 'canonical_c.id', '=', 'c.canonical_id')
            ->leftJoin('project_capabilities AS pc', 'pc.capability_id', '=', 'c.id')
            ->leftJoin('projects AS p', function ($j) {
                $j->on('p.id', '=', 'pc.project_id')->whereNull('p.deleted_at');
            })
            ->select([
                DB::raw('COALESCE(canonical_c.category, c.category) AS category'),
                DB::raw('COUNT(DISTINCT COALESCE(c.canonical_id, c.id)) AS capability_count'),
                DB::raw('COUNT(DISTINCT p.id) AS project_count'),
            ])
            ->groupBy(DB::raw('COALESCE(canonical_c.category, c.category)'))
            ->get();

        // Per-industry totals — distinct projects in that industry that
        // carry at least one capability.
        $industryTotals = DB::table('industries AS i')
            ->leftJoin('project_industries AS pi', 'pi.industry_id', '=', 'i.id')
            ->leftJoin('projects AS p', function ($j) {
                $j->on('p.id', '=', 'pi.project_id')->whereNull('p.deleted_at');
            })
            ->leftJoin('project_capabilities AS pc', 'pc.project_id', '=', 'p.id')
            ->select([
                'i.slug',
                'i.name',
                DB::raw('COUNT(DISTINCT CASE WHEN pc.capability_id IS NOT NULL THEN p.id END) AS project_count'),
            ])
            ->groupBy(['i.slug', 'i.name'])
            ->get();

        $categories = $categoryTotals->map(fn ($r) => [
            'name' => $r->category,
            'capability_count' => (int) $r->capability_count,
            'project_count' => (int) $r->project_count,
        ])->sortByDesc('capability_count')->values()->all();

        $industries = $industryTotals->map(fn ($r) => [
            'slug' => $r->slug,
            'name' => $r->name,
            'project_count' => (int) $r->project_count,
        ])->sortByDesc('project_count')->values()->all();

        return [
            'categories' => $categories,
            'industries' => $industries,
            'cells' => $cells->map(fn ($r) => [
                'category' => $r->category,
                'industry_slug' => $r->industry_slug,
                'project_count' => (int) $r->project_count,
                'capability_count' => (int) $r->capability_count,
            ])->values()->all(),
        ];
    }
}
