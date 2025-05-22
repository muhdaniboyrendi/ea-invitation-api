<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\InvitationController;

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

Route::put('/users/admin/{id}', [UserController::class, 'setAdmin'])->middleware('auth:sanctum');

Route::apiResource('users', UserController::class)->middleware('auth:sanctum');
Route::get('/themes', [ThemeController::class, 'index']);

Route::prefix('packages')->group(function () {
    Route::get('/', [PackageController::class, 'index']);
    Route::get('/{id}', [PackageController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('packages')->group(function () {
        Route::post('/', [PackageController::class, 'store']);
        Route::put('/{id}', [PackageController::class, 'update']);
        Route::delete('/{id}', [PackageController::class, 'destroy']);
    });
    Route::prefix('themes')->group(function () {
        Route::post('/', [ThemeController::class, 'store']);
        Route::get('/{id}', [ThemeController::class, 'show']);
        Route::put('/{id}', [ThemeController::class, 'update']);
        Route::delete('/{id}', [ThemeController::class, 'destroy']);
        Route::get('/{orderId}', [ThemeController::class, 'getThemeByOrderId']);
    });
    Route::prefix('payments')->group(function () {
        Route::post('/create', [OrderController::class, 'createPayment']);
        Route::put('/update', [OrderController::class, 'updatePayment']);
        Route::get('/orders', [OrderController::class, 'getUserOrders']);
        Route::get('/orders/{orderId}', [OrderController::class, 'getOrderStatus']);
        Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancelOrder']);
    });
    Route::prefix('invitations')->group(function () {
        Route::post('/', [InvitationController::class, 'store']);
        Route::put('/{id}', [InvitationController::class, 'update']);
        Route::get('/{id}', [InvitationController::class, 'show']);
        Route::delete('/{id}', [InvitationController::class, 'destroy']);
    });
    Route::get('/orders', [OrderController::class, 'getOrders']);
    Route::get('/order/{order_id}', [OrderController::class, 'getOrder']);
});

Route::get('/categories', [ThemeController::class, 'getCategories']);

Route::post('/payments/notification', [OrderController::class, 'handleNotification']);
Route::post('/payments/recurring-notification', [OrderController::class, 'handleRecurringNotification']);
Route::post('/payments/account-notification', [OrderController::class, 'handleAccountNotification']);