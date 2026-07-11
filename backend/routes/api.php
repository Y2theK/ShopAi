<?php

use App\Http\Controllers\Api\AdminChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::post('/chat', [ChatController::class, 'chat'])->middleware('throttle:chat');
        Route::post('/admin/chat', [AdminChatController::class, 'chat'])->middleware(['admin', 'throttle:admin-chat']);
    });
});
