<?php

use App\Http\Controllers\Api\V1\CapabilityCategoryMatrixController;
use App\Http\Controllers\Api\V1\DrillDownController;
use App\Http\Controllers\Api\V1\HeatmapController;
use App\Http\Controllers\Api\V1\ListCapabilitiesController;
use App\Http\Controllers\Api\V1\ListPackagesController;
use App\Http\Controllers\Api\V1\ListProjectsController;
use App\Http\Controllers\Api\V1\ReportGapsController;
use App\Http\Controllers\Api\V1\ResumeBulletsController;
use App\Http\Controllers\Api\V1\SearchIndexController;
use App\Http\Controllers\Api\V1\ShowCapabilityController;
use App\Http\Controllers\Api\V1\ShowPackageController;
use App\Http\Controllers\Api\V1\ShowProjectController;
use App\Http\Controllers\Api\V1\SignedAssetController;
use Illuminate\Support\Facades\Route;

/*
 * /api/v1/* — public read API. Routes here are auto-prefixed with /api/
 * by withRouting(api: …) in bootstrap/app.php, so the Route::prefix('v1')
 * below produces /api/v1/* as the externally-visible path.
 *
 * Throttled at 60/min/IP per plan §"Rate limiting"; heavy aggregations
 * (/heatmap + /reports/*) sit behind codex.api-heavy (20/min/IP).
 */
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['throttle:codex.api'])
    ->group(function () {
        // Heavy aggregations — tighter limiter.
        Route::middleware(['throttle:codex.api-heavy'])->group(function () {
            Route::get('/capabilities/heatmap', HeatmapController::class)->name('capabilities.heatmap');
            Route::get('/capabilities/category-matrix', CapabilityCategoryMatrixController::class)->name('capabilities.category-matrix');
            Route::get('/reports/gaps', ReportGapsController::class)->name('reports.gaps');
            Route::get('/reports/resume-bullets', ResumeBulletsController::class)->name('reports.resume-bullets');
            Route::get('/search/index', SearchIndexController::class)->name('search.index');
        });

        // Standard endpoints.
        Route::get('/drill-down', DrillDownController::class)->name('drill-down');
        Route::get('/projects', ListProjectsController::class)->name('projects.index');
        Route::get('/projects/{slug}', ShowProjectController::class)
            ->where('slug', '[a-z0-9-]+')
            ->name('projects.show');
        Route::get('/capabilities', ListCapabilitiesController::class)->name('capabilities.index');
        Route::get('/capabilities/{slug}', ShowCapabilityController::class)
            ->where('slug', '[a-z0-9-]+')
            ->name('capabilities.show');
        Route::get('/packages', ListPackagesController::class)->name('packages.index');
        Route::get('/packages/{slug}', ShowPackageController::class)
            ->where('slug', '[a-z0-9-]+')
            ->name('packages.show');
        Route::get('/assets/{ulid}', SignedAssetController::class)
            ->whereAlphaNumeric('ulid')
            ->name('assets.show');
    });

/*
 * Unversioned /api/* fallback — 410 Gone with an RFC 7807 pointer at
 * the versioned URL. Phase 5 wires this; Phase 2 (SDK story) may revisit
 * if a deprecation grace window becomes a buyer requirement.
 *
 * After auto-prefixing this becomes /api/{any}.
 */
Route::any('{any}', function () {
    return response()->json([
        'type' => 'https://codex.philiprehberger.com/problems/unversioned-api',
        'title' => 'Gone',
        'status' => 410,
        'detail' => 'Codex API requires the /v1/ version prefix.',
        'instance' => '/api/v1/',
    ], 410, ['Content-Type' => 'application/problem+json']);
})->where('any', '.*');
