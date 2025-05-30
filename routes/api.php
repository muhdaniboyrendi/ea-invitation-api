<?php

use App\Models\BrideInfo;
use App\Models\GroomInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\GiftInfoController;
use App\Http\Controllers\Api\MainInfoController;
use App\Http\Controllers\Api\BacksoundController;
use App\Http\Controllers\Api\LoveStoryController;
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

// Galleries
Route::get('galleries/public', [GalleryController::class, 'index']);

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

    // Events
    Route::apiResource('events', EventController::class);
    Route::get('invitations/{id}/events', [EventController::class, 'getByInvitation']);
    Route::delete('events/bulk-delete', [EventController::class, 'bulkDelete']);

    // Main Info
    Route::apiResource('main-infos', MainInfoController::class);

    // Groom Info
    Route::apiResource('grooms', GroomInfo::class);

    // Bride Info
    Route::apiResource('brides', BrideInfo::class);

    // Love Stories
    Route::apiResource('love-stories', LoveStoryController::class);
    Route::get('invitations/{id}/love-stories', [LoveStoryController::class, 'getByInvitation']);
    Route::get('invitations/{id}/love-stories/timeline', [LoveStoryController::class, 'timeline']);
    Route::delete('love-stories/bulk-delete', [LoveStoryController::class, 'bulkDelete']);
    Route::patch('love-stories/update-order', [LoveStoryController::class, 'updateOrder']);

    // Gift Infos
    Route::apiResource('gift-infos', GiftInfoController::class);
    Route::post('gift-infos/bulk-update', [GiftInfoController::class, 'bulkUpdate']);

    // Galleris
    Route::apiResource('galleries', GalleryController::class);
    Route::delete('galleries/bulk-destroy', [GalleryController::class, 'bulkDestroy']);
    Route::post('galleries/reorder', [GalleryController::class, 'reorder']);
    
    // ADMIN
    Route::put('/users/admin/{id}', [UserController::class, 'setAdmin']);
    
    Route::apiResource('users', UserController::class);
    
});
