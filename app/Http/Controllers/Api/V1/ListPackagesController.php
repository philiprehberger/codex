<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListPackagesRequest;
use App\Models\Capability;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/packages
 *
 * Cursor-paginated by ULID `id`. Same conventions as ListProjects —
 * orderBy(id), filter allow-list, 422 on unknown query keys.
 */
class ListPackagesController extends Controller
{
    public function __invoke(ListPackagesRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 50);

        $query = Package::query()
            ->with(['capabilities:id,slug,name'])
            ->orderByDesc('id');

        if ($language = $request->validated('language')) {
            $query->where('language', $language);
        }
        if ($registry = $request->validated('registry')) {
            $query->where('registry', $registry);
        }
        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }
        if ($capability = $request->validated('capability')) {
            $query->whereHas('capabilities', fn (Builder $q) => $q->where('capabilities.slug', $capability));
        }

        $page = $query->cursorPaginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Package $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'language' => $p->language,
                'registry' => $p->registry,
                'status' => $p->status,
                'short_description' => $p->short_description,
                'repo_url' => $p->repo_url,
                'registry_url' => $p->registry_url,
                'docs_url' => $p->docs_url,
                'shipped_date' => $p->shipped_date?->toDateString(),
                'capabilities' => $p->capabilities->map(fn (Capability $c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'is_primary' => (bool) $c->pivot->is_primary,
                ])->all(),
            ])->all(),
            'meta' => [
                'next_cursor' => optional($page->nextCursor())->encode(),
                'prev_cursor' => optional($page->previousCursor())->encode(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }
}
