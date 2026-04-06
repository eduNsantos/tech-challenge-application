<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Presentation\Http\Controllers\ServiceController;
use App\Presentation\Http\Controllers\VehicleController;

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
});

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'vehicle'
], function() {
    Route::get('/', [VehicleController::class, 'list']);
    Route::post('/', [VehicleController::class, 'store']);
    Route::get('/{id}', [VehicleController::class, 'show']);
    Route::put('/{id}', [VehicleController::class, 'update']);
});

// TODO: falta route de costumer

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'service'
], function() {
    Route::get('/', [ServiceController::class, 'list']);
    Route::post('/aa', [ServiceController::class, 'store']);
    // Route::get('/{id}', [ServiceController::class, 'show']);
    // Route::put('/{id}', [ServiceController::class, 'update']);
});
