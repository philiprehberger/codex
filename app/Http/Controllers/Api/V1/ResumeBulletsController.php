<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * GET /api/v1/reports/resume-bullets
 *
 * Aggregated bullets by capability / industry / architecture, rendered
 * through Blade templates so the output is testable and reviewable.
 * Bullets are copy-pasteable into resume drafts, Upwork cover letters,
 * and Fiverr gigs.
 *
 * Cached under codex:reports:bullets; invalidated by the Filament
 * observer on any catalogue write.
 */
class ResumeBulletsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('codex:reports:bullets', 3600, function () {
            return [
                'by_capability' => $this->byCapability(),
                'by_industry' => $this->byIndustry(),
                'by_architecture' => $this->byArchitecture(),
            ];
        });

        return response()->json(['data' => $payload]);
    }

    /** @return array<int, array<string, mixed>> */
    private function byCapability(): array
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
            ->havingRaw('COUNT(DISTINCT p.id) > 0')
            ->orderByRaw('COUNT(DISTINCT p.id) DESC')
            ->get([
                'c.slug', 'c.name', 'c.category',
                DB::raw('COUNT(DISTINCT p.id) AS count'),
            ]);

        return $rows->map(fn ($r) => [
            'capability_slug' => $r->slug,
            'capability_name' => $r->name,
            'count' => (int) $r->count,
            'bullet' => trim(View::make('bullets.by_capability', [
                'capability' => $r->name,
                'count' => (int) $r->count,
            ])->render()),
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function byIndustry(): array
    {
        $rows = DB::table('industries AS i')
            ->leftJoin('project_industries AS pi', 'pi.industry_id', '=', 'i.id')
            ->leftJoin('projects AS p', function ($j) {
                $j->on('p.id', '=', 'pi.project_id')->whereNull('p.deleted_at');
            })
            ->groupBy('i.id', 'i.slug', 'i.name')
            ->havingRaw('COUNT(DISTINCT p.id) > 0')
            ->orderByRaw('COUNT(DISTINCT p.id) DESC')
            ->get(['i.slug', 'i.name', DB::raw('COUNT(DISTINCT p.id) AS count')]);

        return $rows->map(fn ($r) => [
            'industry_slug' => $r->slug,
            'industry_name' => $r->name,
            'count' => (int) $r->count,
            'bullet' => trim(View::make('bullets.by_industry', [
                'industry' => $r->name,
                'count' => (int) $r->count,
            ])->render()),
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function byArchitecture(): array
    {
        $rows = DB::table('architectures AS a')
            ->leftJoin('project_architectures AS pa', 'pa.architecture_id', '=', 'a.id')
            ->leftJoin('projects AS p', function ($j) {
                $j->on('p.id', '=', 'pa.project_id')->whereNull('p.deleted_at');
            })
            ->groupBy('a.id', 'a.slug', 'a.name')
            ->havingRaw('COUNT(DISTINCT p.id) > 0')
            ->orderByRaw('COUNT(DISTINCT p.id) DESC')
            ->get(['a.slug', 'a.name', DB::raw('COUNT(DISTINCT p.id) AS count')]);

        return $rows->map(fn ($r) => [
            'architecture_slug' => $r->slug,
            'architecture_name' => $r->name,
            'count' => (int) $r->count,
            'bullet' => trim(View::make('bullets.by_architecture', [
                'architecture' => $r->name,
                'count' => (int) $r->count,
            ])->render()),
        ])->all();
    }
}
