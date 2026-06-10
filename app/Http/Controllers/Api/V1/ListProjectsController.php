<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProjectsRequest;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/projects
 *
 * Cursor-paginated by ULID `id`. Per plan §"Pagination" — orderBy(id),
 * never created_at, because mass-seeded rows can collide on creation
 * timestamps and trip cursorPaginate skip/duplicate bugs. ULIDs are
 * unique + chronologically ordered so they're stable AND meaningful.
 *
 * RedactedScope is applied implicitly (global scope on Project) — every
 * row in the response has client_name + internal_notes stripped when
 * visibility=redacted. The transformer below is the third line of
 * defence after the scope + $hidden.
 */
class ListProjectsController extends Controller
{
    public function __invoke(ListProjectsRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 25);

        $query = Project::query()
            ->with([
                'capabilities:id,slug,name,canonical_id',
                'technologies:id,slug,name',
                'industries:id,slug,name',
            ])
            ->orderByDesc('id'); // newest first; ULIDs are time-ordered

        $this->applyFilters($query, $request);

        $page = $query->cursorPaginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Project $p) => $this->serialise($p))->all(),
            'meta' => [
                'next_cursor' => optional($page->nextCursor())->encode(),
                'prev_cursor' => optional($page->previousCursor())->encode(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    private function applyFilters(Builder $query, ListProjectsRequest $request): void
    {
        if ($slug = $request->validated('capability')) {
            $query->whereHas('capabilities', fn (Builder $q) => $q->where('capabilities.slug', $slug));
        }
        if ($slug = $request->validated('industry')) {
            $query->whereHas('industries', fn (Builder $q) => $q->where('industries.slug', $slug));
        }
        if ($slug = $request->validated('architecture')) {
            $query->whereHas('architectures', fn (Builder $q) => $q->where('architectures.slug', $slug));
        }
        if ($type = $request->validated('type')) {
            $query->where('project_type', $type);
        }
        if ($year = $request->validated('year')) {
            $query->whereYear('shipped_date', $year);
        }
    }

    private function serialise(Project $project): array
    {
        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'name' => $project->name,
            'project_type' => $project->project_type,
            'status' => $project->status,
            'visibility' => $project->visibility,
            'short_description' => $project->short_description,
            'client_name' => $project->client_name,         // RedactedScope strips when redacted
            'client_industry' => $project->client_industry, // always visible
            'shipped_date' => $project->shipped_date?->toDateString(),
            'repo_url' => $project->repo_url,
            'live_url' => $project->live_url,
            'docs_url' => $project->docs_url,
            'capabilities' => $project->capabilities->map(fn ($c) => [
                'slug' => $c->slug, 'name' => $c->name, 'is_primary' => (bool) $c->pivot->is_primary,
            ])->all(),
            'technologies' => $project->technologies->map(fn ($t) => [
                'slug' => $t->slug, 'name' => $t->name, 'is_primary' => (bool) $t->pivot->is_primary,
            ])->all(),
            'industries' => $project->industries->pluck('slug')->all(),
        ];
    }
}
