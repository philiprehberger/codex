<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Capability;
use App\Models\Package;
use Illuminate\Http\JsonResponse;

class ShowPackageController extends Controller
{
    public function __invoke(string $slug): JsonResponse
    {
        $package = Package::query()
            ->with(['capabilities:id,slug,name,category,canonical_id'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $package->id,
                'slug' => $package->slug,
                'name' => $package->name,
                'language' => $package->language,
                'registry' => $package->registry,
                'status' => $package->status,
                'short_description' => $package->short_description,
                'long_description' => $package->long_description,
                'repo_url' => $package->repo_url,
                'registry_url' => $package->registry_url,
                'docs_url' => $package->docs_url,
                'shipped_date' => $package->shipped_date?->toDateString(),
                'capabilities' => $package->capabilities->map(fn (Capability $c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'category' => $c->category,
                    'canonical_slug' => $c->resolveCanonical()->slug,
                    'is_primary' => (bool) $c->pivot->is_primary,
                ])->all(),
            ],
        ]);
    }
}
