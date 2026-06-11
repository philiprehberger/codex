<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/reports/gaps
 *
 * Two payloads in one response:
 *  1. capabilities used 0-2 times — the "what's missing" honesty signal
 *     that's a load-bearing differentiator vs portfolios that hide gaps
 *  2. tech × industry coverage matrix — every combination of
 *     technology + industry, with the project count for each cell.
 *     Sparse so the unworked combinations stand out.
 *
 * Cached under codex:reports:gaps; invalidated by the Filament observer.
 */
class ReportGapsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('codex:reports:gaps', 3600, function () {
            return [
                'capability_gaps' => $this->capabilityGaps(),
                'tech_industry_coverage' => $this->techIndustryCoverage(),
            ];
        });

        return response()->json(['data' => $payload]);
    }

    /**
     * Gap = a capability with <=2 projects AND <=2 packages. The combined
     * filter keeps the buyer-honest signal honest: a capability with 0
     * projects but 20 packages is well-demonstrated, not a gap. Sorted
     * by combined coverage so the truly empty entries surface first.
     *
     * Project + package counts are independent aggregates joined back
     * onto the capability via COALESCE(canonical_id, id) for alias
     * rollup; soft-deleted rows filtered on both sides.
     *
     * @return array<int, array<string, mixed>>
     */
    private function capabilityGaps(): array
    {
        $projectCounts = DB::table('project_capabilities AS pc')
            ->join('projects AS p', function ($j) {
                $j->on('p.id', '=', 'pc.project_id')->whereNull('p.deleted_at');
            })
            ->select('pc.capability_id', DB::raw('COUNT(DISTINCT p.id) AS n'))
            ->groupBy('pc.capability_id');

        $packageCounts = DB::table('package_capabilities AS pkc')
            ->join('packages AS pk', function ($j) {
                $j->on('pk.id', '=', 'pkc.package_id')->whereNull('pk.deleted_at');
            })
            ->select('pkc.capability_id', DB::raw('COUNT(DISTINCT pk.id) AS n'))
            ->groupBy('pkc.capability_id');

        $rows = DB::table('capabilities AS c')
            ->leftJoinSub($projectCounts, 'proj', fn ($j) => $j->on('proj.capability_id', '=', DB::raw('COALESCE(c.canonical_id, c.id)')))
            ->leftJoinSub($packageCounts, 'pkg', fn ($j) => $j->on('pkg.capability_id', '=', DB::raw('COALESCE(c.canonical_id, c.id)')))
            ->whereNull('c.canonical_id')
            ->whereRaw('COALESCE(proj.n, 0) <= 2')
            ->whereRaw('COALESCE(pkg.n, 0) <= 2')
            ->orderByRaw('COALESCE(proj.n, 0) + COALESCE(pkg.n, 0) ASC')
            ->orderBy('c.name')
            ->get([
                'c.id', 'c.slug', 'c.name', 'c.category',
                DB::raw('COALESCE(proj.n, 0) AS project_count'),
                DB::raw('COALESCE(pkg.n, 0) AS package_count'),
            ]);

        return $rows->map(fn ($r) => [
            'slug' => $r->slug,
            'name' => $r->name,
            'category' => $r->category,
            'count' => (int) $r->project_count,
            'project_count' => (int) $r->project_count,
            'package_count' => (int) $r->package_count,
        ])->all();
    }

    /**
     * Matrix of technology × industry, with project counts per cell. Packages
     * don't carry industry tags (they're inherently developer-tools), so
     * package_technologies counts are added to the `developer-tools` row
     * for each technology — the only cell where they'd land if industries
     * existed for packages. Without this addition, e.g. GitHub Actions would
     * read "1" because only Shipyard tags it among projects, despite every
     * package's release pipeline depending on it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function techIndustryCoverage(): array
    {
        $rows = DB::table('technologies AS t')
            ->crossJoin('industries AS i')
            ->leftJoin('project_technologies AS pt', 't.id', '=', 'pt.technology_id')
            ->leftJoin('project_industries AS pi', function ($j) {
                $j->on('pi.industry_id', '=', 'i.id')
                    ->on('pi.project_id', '=', 'pt.project_id');
            })
            ->leftJoin('projects AS p', function ($j) {
                $j->on('p.id', '=', 'pt.project_id')->whereNull('p.deleted_at');
            })
            ->select([
                't.id AS technology_id',
                't.slug AS technology_slug',
                't.name AS technology_name',
                'i.slug AS industry_slug',
                'i.name AS industry_name',
                DB::raw('COUNT(DISTINCT CASE WHEN pi.project_id IS NOT NULL THEN p.id END) AS count'),
            ])
            ->groupBy('t.id', 't.slug', 't.name', 'i.id', 'i.slug', 'i.name')
            ->get();

        $packageCountsByTechId = DB::table('package_technologies AS pkt')
            ->join('packages AS pk', function ($j) {
                $j->on('pk.id', '=', 'pkt.package_id')->whereNull('pk.deleted_at');
            })
            ->select('pkt.technology_id', DB::raw('COUNT(DISTINCT pkt.package_id) AS n'))
            ->groupBy('pkt.technology_id')
            ->pluck('n', 'technology_id');

        return $rows->map(function ($r) use ($packageCountsByTechId) {
            $count = (int) $r->count;
            if ($r->industry_slug === 'developer-tools' && isset($packageCountsByTechId[$r->technology_id])) {
                $count += (int) $packageCountsByTechId[$r->technology_id];
            }

            return [
                'technology_slug' => $r->technology_slug,
                'technology_name' => $r->technology_name,
                'industry_slug' => $r->industry_slug,
                'industry_name' => $r->industry_name,
                'count' => $count,
            ];
        })->all();
    }
}
