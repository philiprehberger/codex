<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DrillDownRequest;
use App\Models\Architecture;
use App\Models\Capability;
use App\Models\Industry;
use App\Models\Package;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/drill-down
 *
 * One endpoint, five scopes — drives the click-to-modal interactions
 * on /heatmap and /resume-bullets. Exactly one of the following query
 * keys must be set (category may pair with industry):
 *
 *  ?capability=auth
 *  ?industry=saas
 *  ?architecture=monolith
 *  ?category=Automation
 *  ?category=Automation&industry=saas         (cell drill-down)
 *
 * Response shape:
 *  {
 *    scope:    { type, slug|name, label },
 *    title:    "Authentication",
 *    subtitle: "14 projects · 3 packages",
 *    projects: [{ slug, name, short_description, project_type, status,
 *                 visibility, shipped_date, client_industry }],
 *    packages: [{ slug, name, short_description, language, registry,
 *                 status, repo_url, shipped_date }]
 *  }
 *
 * No caching — clicks are infrequent and the queries hit indexed pivots.
 * RedactedScope still applies to projects so client_name/internal_notes
 * stay stripped for visibility=redacted rows.
 */
class DrillDownController extends Controller
{
    public function __invoke(DrillDownRequest $request): JsonResponse
    {
        $capability = $request->validated('capability');
        $industry = $request->validated('industry');
        $architecture = $request->validated('architecture');
        $category = $request->validated('category');

        if ($capability !== null) {
            return $this->byCapability($capability);
        }
        if ($architecture !== null) {
            return $this->byArchitecture($architecture);
        }
        if ($category !== null && $industry !== null) {
            return $this->byCell($category, $industry);
        }
        if ($category !== null) {
            return $this->byCategory($category);
        }
        if ($industry !== null) {
            return $this->byIndustry($industry);
        }

        return response()->json([
            'type' => 'https://codex.philiprehberger.com/problems/drill-down-missing-scope',
            'title' => 'Missing scope',
            'status' => 422,
            'detail' => 'Provide one of: capability, industry, architecture, category (with optional industry).',
        ], 422, ['Content-Type' => 'application/problem+json']);
    }

    private function byCapability(string $slug): JsonResponse
    {
        $cap = Capability::query()->where('slug', $slug)->first();
        if ($cap === null) {
            return $this->notFound('capability', $slug);
        }
        $canonicalId = $cap->canonical_id ?? $cap->id;

        $projects = Project::query()
            ->whereHas('capabilities', function (Builder $q) use ($canonicalId) {
                $q->where(function (Builder $inner) use ($canonicalId) {
                    $inner->where('capabilities.id', $canonicalId)
                        ->orWhere('capabilities.canonical_id', $canonicalId);
                });
            })
            ->orderByDesc('id')
            ->get();

        $packages = Package::query()
            ->whereHas('capabilities', function (Builder $q) use ($canonicalId) {
                $q->where(function (Builder $inner) use ($canonicalId) {
                    $inner->where('capabilities.id', $canonicalId)
                        ->orWhere('capabilities.canonical_id', $canonicalId);
                });
            })
            ->orderByDesc('id')
            ->get();

        return $this->respond(
            scope: ['type' => 'capability', 'slug' => $cap->slug, 'label' => $cap->name],
            title: $cap->name,
            projects: $projects,
            packages: $packages,
        );
    }

    private function byIndustry(string $slug): JsonResponse
    {
        $industry = Industry::query()->where('slug', $slug)->first();
        if ($industry === null) {
            return $this->notFound('industry', $slug);
        }

        $projects = Project::query()
            ->whereHas('industries', fn (Builder $q) => $q->where('industries.id', $industry->id))
            ->orderByDesc('id')
            ->get();

        return $this->respond(
            scope: ['type' => 'industry', 'slug' => $industry->slug, 'label' => $industry->name],
            title: $industry->name,
            projects: $projects,
            packages: null,
        );
    }

    private function byArchitecture(string $slug): JsonResponse
    {
        $architecture = Architecture::query()->where('slug', $slug)->first();
        if ($architecture === null) {
            return $this->notFound('architecture', $slug);
        }

        $projects = Project::query()
            ->whereHas('architectures', fn (Builder $q) => $q->where('architectures.id', $architecture->id))
            ->orderByDesc('id')
            ->get();

        return $this->respond(
            scope: ['type' => 'architecture', 'slug' => $architecture->slug, 'label' => $architecture->name],
            title: $architecture->name,
            projects: $projects,
            packages: null,
        );
    }

    private function byCategory(string $category): JsonResponse
    {
        // Canonical capability ids in this category — aliases resolve up.
        $canonicalIds = Capability::query()
            ->whereNull('canonical_id')
            ->where('category', $category)
            ->pluck('id');

        $projects = Project::query()
            ->whereHas('capabilities', function (Builder $q) use ($canonicalIds) {
                $q->where(function (Builder $inner) use ($canonicalIds) {
                    $inner->whereIn('capabilities.id', $canonicalIds)
                        ->orWhereIn('capabilities.canonical_id', $canonicalIds);
                });
            })
            ->orderByDesc('id')
            ->get();

        $packages = Package::query()
            ->whereHas('capabilities', function (Builder $q) use ($canonicalIds) {
                $q->where(function (Builder $inner) use ($canonicalIds) {
                    $inner->whereIn('capabilities.id', $canonicalIds)
                        ->orWhereIn('capabilities.canonical_id', $canonicalIds);
                });
            })
            ->orderByDesc('id')
            ->get();

        return $this->respond(
            scope: ['type' => 'category', 'label' => $category],
            title: $category,
            projects: $projects,
            packages: $packages,
        );
    }

    private function byCell(string $category, string $industrySlug): JsonResponse
    {
        $industry = Industry::query()->where('slug', $industrySlug)->first();
        if ($industry === null) {
            return $this->notFound('industry', $industrySlug);
        }

        $canonicalIds = Capability::query()
            ->whereNull('canonical_id')
            ->where('category', $category)
            ->pluck('id');

        $projects = Project::query()
            ->whereHas('industries', fn (Builder $q) => $q->where('industries.id', $industry->id))
            ->whereHas('capabilities', function (Builder $q) use ($canonicalIds) {
                $q->where(function (Builder $inner) use ($canonicalIds) {
                    $inner->whereIn('capabilities.id', $canonicalIds)
                        ->orWhereIn('capabilities.canonical_id', $canonicalIds);
                });
            })
            ->orderByDesc('id')
            ->get();

        return $this->respond(
            scope: ['type' => 'cell', 'label' => "{$category} × {$industry->name}"],
            title: "{$category} × {$industry->name}",
            projects: $projects,
            packages: null,
        );
    }

    /**
     * @param  array<string, string>  $scope
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, Package>|null  $packages
     */
    private function respond(array $scope, string $title, Collection $projects, ?Collection $packages): JsonResponse
    {
        $projectCount = $projects->count();
        $packageCount = $packages?->count() ?? 0;
        $parts = [];
        if ($projectCount > 0) {
            $parts[] = $projectCount.' project'.($projectCount === 1 ? '' : 's');
        }
        if ($packageCount > 0) {
            $parts[] = $packageCount.' package'.($packageCount === 1 ? '' : 's');
        }
        $subtitle = $parts === [] ? 'No matches' : implode(' · ', $parts);

        $projectCards = [];
        foreach ($projects as $p) {
            $projectCards[] = [
                'slug' => $p->slug,
                'name' => $p->name,
                'short_description' => $p->short_description,
                'project_type' => $p->project_type,
                'status' => $p->status,
                'visibility' => $p->visibility,
                'shipped_date' => $p->shipped_date?->toDateString(),
                'client_industry' => $p->client_industry, // visible even when redacted
            ];
        }
        $packageCards = [];
        foreach ($packages ?? [] as $pkg) {
            $packageCards[] = [
                'slug' => $pkg->slug,
                'name' => $pkg->name,
                'short_description' => $pkg->short_description,
                'language' => $pkg->language,
                'registry' => $pkg->registry,
                'status' => $pkg->status,
                'repo_url' => $pkg->repo_url,
                'shipped_date' => $pkg->shipped_date?->toDateString(),
            ];
        }

        return response()->json(['data' => [
            'scope' => $scope,
            'title' => $title,
            'subtitle' => $subtitle,
            'projects' => $projectCards,
            'packages' => $packageCards,
        ]]);
    }

    private function notFound(string $kind, string $value): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Not found',
            'status' => 404,
            'detail' => "No {$kind} matches '{$value}'.",
        ], 404, ['Content-Type' => 'application/problem+json']);
    }
}
