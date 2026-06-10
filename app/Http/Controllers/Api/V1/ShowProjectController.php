<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/projects/{slug}
 *
 * Full project detail with all 7 tag relations + assets + metrics +
 * (public) learnings. RedactedScope strips client_name + internal_notes
 * on visibility=redacted rows. internal_notes is also $hidden, so even
 * a hypothetical unscoped serialisation wouldn't leak it.
 *
 * 404 (rendered through the RFC 7807 handler) when no row matches the
 * slug. Slugs (not ULIDs) are the URL identifier for SEO + memorability,
 * per the ULID-vs-slug convention block in docs/api-conventions.md.
 */
class ShowProjectController extends Controller
{
    public function __invoke(string $slug): JsonResponse
    {
        $project = Project::query()
            ->with([
                'capabilities:id,slug,name,canonical_id,category',
                'technologies:id,slug,name,category',
                'industries:id,slug,name',
                'architectures:id,slug,name,description',
                'deliverables:id,slug,name',
                'designStyles:id,slug,name',
                'tags:id,slug,name',
                'assets:id,project_id,asset_type,path,caption,display_order,og_path',
                'metrics' => fn ($q) => $q->orderByDesc('recorded_at')->limit(1),
                'learnings' => fn ($q) => $q->where('visibility', 'public'),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'name' => $project->name,
                'project_type' => $project->project_type,
                'status' => $project->status,
                'visibility' => $project->visibility,
                'short_description' => $project->short_description,
                'long_description' => $project->long_description,
                'client_name' => $project->client_name,
                'client_industry' => $project->client_industry,
                'shipped_date' => $project->shipped_date?->toDateString(),
                'hours_estimated' => $project->hours_estimated,
                'hours_actual' => $project->hours_actual,
                'team_size' => $project->team_size,
                'repo_url' => $project->repo_url,
                'live_url' => $project->live_url,
                'docs_url' => $project->docs_url,
                'capabilities' => $project->capabilities->map(fn ($c) => [
                    'slug' => $c->slug, 'name' => $c->name, 'category' => $c->category,
                    'is_primary' => (bool) $c->pivot->is_primary,
                    'canonical_slug' => $c->resolveCanonical()->slug,
                ])->all(),
                'technologies' => $project->technologies->map(fn ($t) => [
                    'slug' => $t->slug, 'name' => $t->name, 'category' => $t->category,
                    'is_primary' => (bool) $t->pivot->is_primary,
                ])->all(),
                'industries' => $project->industries->map(fn ($i) => ['slug' => $i->slug, 'name' => $i->name])->all(),
                'architectures' => $project->architectures->map(fn ($a) => ['slug' => $a->slug, 'name' => $a->name])->all(),
                'deliverables' => $project->deliverables->map(fn ($d) => ['slug' => $d->slug, 'name' => $d->name])->all(),
                'design_styles' => $project->designStyles->map(fn ($d) => ['slug' => $d->slug, 'name' => $d->name])->all(),
                'tags' => $project->tags->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name])->all(),
                'assets' => $project->assets->map(fn ($a) => [
                    'id' => $a->id, 'type' => $a->asset_type, 'path' => $a->path,
                    'caption' => $a->caption, 'display_order' => $a->display_order,
                ])->all(),
                'latest_metrics' => $project->metrics->first()
                    ? [
                        'recorded_at' => $project->metrics->first()->recorded_at?->toDateString(),
                        'duration_days' => $project->metrics->first()->duration_days,
                        'api_integrations' => $project->metrics->first()->api_integrations,
                        'database_tables' => $project->metrics->first()->database_tables,
                        'test_count' => $project->metrics->first()->test_count,
                        'lighthouse' => [
                            'perf' => $project->metrics->first()->lighthouse_perf,
                            'a11y' => $project->metrics->first()->lighthouse_a11y,
                            'best' => $project->metrics->first()->lighthouse_best,
                            'seo' => $project->metrics->first()->lighthouse_seo,
                        ],
                    ]
                    : null,
                'learnings' => $project->learnings->map(fn ($l) => [
                    'title' => $l->title, 'description' => $l->description,
                ])->all(),
            ],
        ]);
    }
}
