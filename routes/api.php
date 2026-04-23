<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Presentation\Http\Controllers\ServiceOrderController;
use App\Presentation\Http\Controllers\ItemController;
use App\Presentation\Http\Controllers\StockController;
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
], function () {
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

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'item'
], function () {
    Route::get('/', [ItemController::class, 'list']);
    Route::post('/', [ItemController::class, 'store']);
    Route::get('/{id}', [ItemController::class, 'show']);
    Route::put('/{id}', [ItemController::class, 'update']);
    Route::delete('/{id}', [ItemController::class, 'destroy']);

    Route::post('/{id}/stock/entry', [StockController::class, 'entry']);
    Route::post('/{id}/stock/withdrawal', [StockController::class, 'withdrawal']);
    Route::get('/{id}/stock/movements', [StockController::class, 'movements']);
});
// TODO: falta route de costumer

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'service'
], function () {
    Route::get('/', [ServiceController::class, 'list']);
    Route::post('/', [ServiceController::class, 'store']);
    Route::get('/{id}', [ServiceController::class, 'show']);
    Route::put('/{id}', [ServiceController::class, 'update']);
});
