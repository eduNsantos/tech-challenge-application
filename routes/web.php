<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'tech-challenge-application',
    ]);
});

Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'tech-challenge-application',
    ]);
});
