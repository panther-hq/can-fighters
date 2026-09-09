<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CanController;
use App\Http\Controllers\FighterController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MixerController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Auth is Sanctum SPA cookie based: first-party requests go through the
| session + CSRF stack (see bootstrap/app.php `statefulApi()`).
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
    } catch (Throwable $e) {
        // reported as false below
    }

    try {
        Cache::store()->put('health:ping', 'pong', 5);
        $checks['cache'] = Cache::store()->get('health:ping') === 'pong';
    } catch (Throwable $e) {
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

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('game/bootstrap', [GameController::class, 'bootstrap']);

    Route::get('ingredients', [IngredientController::class, 'index']);
    Route::get('player/inventory', [InventoryController::class, 'show']);

    Route::get('cans', [CanController::class, 'index']);
    Route::post('cans/{can}/open', [CanController::class, 'open']);

    Route::post('mixer/preview', [MixerController::class, 'preview']);
    Route::post('mixer/mix', [MixerController::class, 'mix']);
    Route::get('mixer/{mix}', [MixerController::class, 'show']);

    Route::get('fighters', [FighterController::class, 'index']);
    Route::get('fighters/{fighter}', [FighterController::class, 'show']);
    Route::post('fighters/{fighter}/upgrade', [FighterController::class, 'upgrade']);
    Route::post('fighters/{fighter}/mutate', [FighterController::class, 'mutate']);

    Route::get('teams', [TeamController::class, 'show']);
    Route::put('teams', [TeamController::class, 'update']);
});
