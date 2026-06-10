<?php

use App\Http\Controllers\Api\V1\DiagnosticsController;
use App\Http\Controllers\Api\V1\QueueHeartbeatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Codex-extended health endpoints. Registered in web.php (not api.php)
 * so they don't pick up the /api/ prefix or codex.api throttle —
 * BetterStack pings /up/diagnostics + /up/queue on the api host every
 * 60s and would otherwise eat the rate-limit budget. /up is Laravel's
 * default and lives at bootstrap/app.php → withRouting(health: '/up').
 */
Route::get('/up/diagnostics', DiagnosticsController::class)->name('up.diagnostics');
Route::get('/up/queue', QueueHeartbeatController::class)->name('up.queue');
