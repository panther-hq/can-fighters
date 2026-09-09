<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Phase 0 — running skeleton only. The health check exercises the full
| request path: PHP -> PostgreSQL -> Redis, so the SPA can prove the
| stack is wired end to end before any game feature exists.
|
*/

Route::get('/health', function () {
    $checks = [
        'database' => false,
        'cache' => false,
    ];

    try {
        DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Throwable $e) {
        // reported as false below
    }

    try {
        Cache::store()->put('health:ping', 'pong', 5);
        $checks['cache'] = Cache::store()->get('health:ping') === 'pong';
    } catch (\Throwable $e) {
        // reported as false below
    }

    $ok = ! in_array(false, $checks, true);

    return response()->json([
        'status' => $ok ? 'ok' : 'degraded',
        'service' => 'can-fighters-api',
        'checks' => $checks,
        'time' => now()->toIso8601String(),
    ], $ok ? 200 : 503);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
