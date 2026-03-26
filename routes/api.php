<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Interfaces\Http\Controllers\VehicleController;

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
});

