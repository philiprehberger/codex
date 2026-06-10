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

    /** @return array<int, array<string, mixed>> */
    private function capabilityGaps(): array
    {
        $rows = DB::table('capabilities AS c')
            ->leftJoin('project_capabilities AS pc',
                fn ($j) => $j->on(DB::raw('COALESCE(c.canonical_id, c.id)'), '=', 'pc.capability_id'),
            )
            ->leftJoin('projects AS p', function ($j) {
                $j->on('p.id', '=', 'pc.project_id')->whereNull('p.deleted_at');
            })
            ->whereNull('c.canonical_id')
            ->groupBy('c.id', 'c.slug', 'c.name', 'c.category')
            ->havingRaw('COUNT(DISTINCT p.id) <= 2')
            ->orderByRaw('COUNT(DISTINCT p.id) ASC')
            ->orderBy('c.name')
            ->get([
                'c.id', 'c.slug', 'c.name', 'c.category',
                DB::raw('COUNT(DISTINCT p.id) AS count'),
            ]);

        return $rows->map(fn ($r) => [
            'slug' => $r->slug,
            'name' => $r->name,
            'category' => $r->category,
            'count' => (int) $r->count,
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
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
                't.slug AS technology_slug',
                't.name AS technology_name',
                'i.slug AS industry_slug',
                'i.name AS industry_name',
                DB::raw('COUNT(DISTINCT CASE WHEN pi.project_id IS NOT NULL THEN p.id END) AS count'),
            ])
            ->groupBy('t.id', 't.slug', 't.name', 'i.id', 'i.slug', 'i.name')
            ->get();

        return $rows->map(fn ($r) => [
            'technology_slug' => $r->technology_slug,
            'technology_name' => $r->technology_name,
            'industry_slug' => $r->industry_slug,
            'industry_name' => $r->industry_name,
            'count' => (int) $r->count,
        ])->all();
    }
}
