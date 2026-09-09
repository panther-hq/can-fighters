<?php

use App\Http\Controllers\ArenaController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CanController;
use App\Http\Controllers\DailyController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FighterController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LiveBattleController;
use App\Http\Controllers\MixerController;
use App\Http\Controllers\PveController;
use App\Http\Controllers\ShopController;
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

    Route::get('daily', [DailyController::class, 'show']);
    Route::post('daily/claim', [DailyController::class, 'claim']);
    Route::get('shop', [ShopController::class, 'index']);
    Route::post('shop/{offer}/buy', [ShopController::class, 'buy']);

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
    Route::post('fighters/{fighter}/equip', [FighterController::class, 'equip']);
    Route::post('fighters/{fighter}/unequip', [FighterController::class, 'unequip']);

    Route::get('equipment', [EquipmentController::class, 'index']);

    Route::get('teams', [TeamController::class, 'show']);
    Route::put('teams', [TeamController::class, 'update']);

    Route::get('pve/stages', [PveController::class, 'stages']);
    Route::post('pve/stages/{stage:slug}/battle', [PveController::class, 'battle']);
    Route::get('pve/battles/{battle}', [PveController::class, 'battleShow']);

    Route::get('arena', [ArenaController::class, 'show']);
    Route::put('arena/defense-team', [ArenaController::class, 'setDefenseTeam']);
    Route::get('arena/opponents', [ArenaController::class, 'opponents']);
    Route::get('arena/ranking', [ArenaController::class, 'ranking']);
    Route::get('arena/history', [ArenaController::class, 'history']);
    Route::post('arena/challenge/{player}', [ArenaController::class, 'challenge']);

    Route::post('arena/live/queue', [LiveBattleController::class, 'queue']);
    Route::delete('arena/live/queue', [LiveBattleController::class, 'leaveQueue']);
    Route::get('battles/{liveBattle}', [LiveBattleController::class, 'show']);
    Route::post('battles/{liveBattle}/actions', [LiveBattleController::class, 'act']);
    Route::post('battles/{liveBattle}/resolve', [LiveBattleController::class, 'resolve']);
});
