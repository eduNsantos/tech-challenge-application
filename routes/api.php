<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Presentation\Http\Controllers\ServiceOrderController;
use App\Presentation\Http\Controllers\VehicleController;

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
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

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'service-order'
], function () {
    Route::get('/', [ServiceOrderController::class, 'list']);
    Route::post('/', [ServiceOrderController::class, 'store']);
    Route::get('/{id}', [ServiceOrderController::class, 'show']);
    Route::put('/{id}', [ServiceOrderController::class, 'update']);
    Route::patch('/{id}/status', [ServiceOrderController::class, 'updateStatus']);
    Route::delete('/{id}', [ServiceOrderController::class, 'destroy']);
});

