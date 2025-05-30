<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\BacksoundController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ThemeCategoryController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Google Auth
Route::prefix('auth')->group(function () {
    Route::get('/google', [GoogleAuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
});

// Packages
Route::prefix('packages')->group(function () {
    Route::get('/', [PackageController::class, 'index']);
    Route::get('/{id}', [PackageController::class, 'show']);
});

// Themes
Route::prefix('themes')->group(function () {
    Route::get('/', [ThemeController::class, 'index']);
    Route::get('/categories', [ThemeCategoryController::class, 'index']);
    Route::get('/{id}', [ThemeController::class, 'show']);
});

// Backsounds
Route::prefix('backsounds')->group(function () {
    Route::get('/', [BacksoundController::class, 'index']);
    Route::get('/{id}', [BacksoundController::class, 'show']);
});

// Guest
Route::get('/guest/{slug}', [GuestController::class, 'getGuestBySlug']);
Route::put('/rsvp/{slug}', [GuestController::class, 'rsvp']);

// Payments
Route::prefix('payments')->group(function () {
    Route::post('/notification', [PaymentController::class, 'handleNotification']);
    Route::post('/recurring-notification', [PaymentController::class, 'handleRecurringNotification']);
    Route::post('/account-notification', [PaymentController::class, 'handleAccountNotification']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Packages
    Route::apiResource('packages', PackageController::class)->except(['index', 'show']);

    // Themes
    Route::apiResource('themes', ThemeController::class)->except(['index', 'show']);
    Route::post('/themes/orderthemes', [ThemeController::class, 'getThemeByOrderId']);

    // Backsounds
    Route::apiResource('backsounds', BacksoundController::class)->except(['index', 'show']);
    
    // Invitations
    Route::prefix('invitations')->group(function () {
        Route::post('/', [InvitationController::class, 'store']);
        Route::post('/check', [InvitationController::class, 'checkByOrderId']);
    });

    // Guests
    Route::apiResource('guests', GuestController::class)->except(['index', 'show']);
    Route::prefix('guests')->group(function () {
        Route::get('/{invitationId}', [GuestController::class, 'getGuestsByInvitationId']);
    });

    // Payments
    Route::prefix('payments')->group(function () {
        Route::post('/create', [PaymentController::class, 'createPayment']);
        Route::put('/update', [PaymentController::class, 'updatePayment']);
        Route::get('/orders', [OrderController::class, 'getUserOrders']);
        Route::get('/orders/{orderId}', [OrderController::class, 'getOrderStatus']);
        Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancelOrder']);
    });
    
    // Orders
    Route::get('/orders', [OrderController::class, 'getOrders']);
    Route::get('/order/{order_id}', [OrderController::class, 'getOrder']);
    
    // ADMIN
    Route::put('/users/admin/{id}', [UserController::class, 'setAdmin']);
    
    Route::apiResource('users', UserController::class);
    
});
