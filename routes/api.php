<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\OrderController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::get('/google', [GoogleAuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::apiResource('users', UserController::class);
Route::apiResource('packages', PackageController::class);
Route::apiResource('themes', ThemeController::class);

// Route::prefix('themes')->group(function () {
//     Route::get('/', [ThemeController::class, 'index']);
//     Route::post('/', [ThemeController::class, 'store']);
//     Route::get('/{id}', [ThemeController::class, 'show']);
//     Route::put('/{id}', [ThemeController::class, 'update']);
//     Route::delete('/{id}', [ThemeController::class, 'destroy']);
// });

Route::get('/categories', [ThemeController::class, 'getCategories']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('payments')->group(function () {
        Route::post('/create', [OrderController::class, 'createPayment']);
        Route::get('/orders', [OrderController::class, 'getUserOrders']);
        Route::get('/orders/{orderId}', [OrderController::class, 'getOrderStatus']);
        Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancelOrder']);
    });
});

Route::post('/payments/notification', [OrderController::class, 'handleNotification']);