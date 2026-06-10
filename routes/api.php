<?php

use App\Http\Controllers\Api\V1\SignedAssetController;
use Illuminate\Support\Facades\Route;

// Phase 3 — only the signed-URL asset route. Phase 5 fills out
// /api/v1/projects, /api/v1/capabilities, /api/v1/reports/*, etc.
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('assets/{ulid}', SignedAssetController::class)
        ->whereAlphaNumeric('ulid')
        ->name('assets.show');
});

// Unversioned /api/* → 410 Gone with an RFC 7807 pointer at /api/v1/.
// Phase 5 wires the formal handler; for Phase 3 this is the explicit
// fall-through.
Route::any('{any}', function () {
    return response()->json([
        'type' => 'https://codex.philiprehberger.com/problems/unversioned-api',
        'title' => 'Gone',
        'status' => 410,
        'detail' => 'Codex API requires the /v1/ version prefix.',
        'instance' => '/api/v1/',
    ], 410, ['Content-Type' => 'application/problem+json']);
})->where('any', '.*');
