<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\EpisodeController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

// Todas las rutas de este fichero cuelgan de /api (ver bootstrap/app.php).

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Consulta de los datos sincronizados. Son públicos: no hace falta token.
Route::apiResource('characters', CharacterController::class)->only(['index', 'show']);
Route::apiResource('episodes', EpisodeController::class)->only(['index', 'show']);
Route::apiResource('locations', LocationController::class)->only(['index', 'show']);

// Favoritos del usuario autenticado.
Route::middleware('auth:api')->group(function () {
    Route::apiResource('favorites', FavoriteController::class)
        ->only(['index', 'store', 'destroy'])
        ->parameters(['favorites' => 'character']); // DELETE /api/favorites/{character}
});
