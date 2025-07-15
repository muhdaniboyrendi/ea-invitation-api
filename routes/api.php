<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    UserController,
    EventController,
    GroomController,
    GuestController,
    OrderController,
    ThemeController,
    GalleryController,
    PackageController,
    PaymentController,
    GiftInfoController,
    MainInfoController,
    BacksoundController,
    BrideController,
    LoveStoryController,
    GoogleAuthController,
    InvitationController,
    ThemeCategoryController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Google Auth
Route::prefix('auth')->group(function () {
    Route::get('/google', [GoogleAuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
});

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/

// Packages (Public)
Route::prefix('packages')->group(function () {
    Route::get('/', [PackageController::class, 'index']);
    Route::get('/{id}', [PackageController::class, 'show']);
});

// Themes (Public)
Route::prefix('themes')->group(function () {
    Route::get('/', [ThemeController::class, 'index']);
    Route::get('/categories', [ThemeCategoryController::class, 'index']);
    Route::get('/{id}', [ThemeController::class, 'show']);
});

// Theme Categories (Public)
Route::prefix('categories')->group(function () {
    Route::get('/', [ThemeCategoryController::class, 'index']);
});

// Backsounds (Public)
Route::prefix('backsounds')->group(function () {
    Route::get('/', [BacksoundController::class, 'index']);
    Route::get('/{id}', [BacksoundController::class, 'show']);
});

// Guests (Public)
Route::get('/guests/{invitationId}', [GuestController::class, 'getGuestsByInvitationId']);
Route::get('/guest/{slug}', [GuestController::class, 'getGuestBySlug']);
Route::put('/rsvp/{slug}', [GuestController::class, 'rsvp']);

// Payment Webhooks (Public)
Route::prefix('payments')->group(function () {
    Route::post('/notification', [PaymentController::class, 'handleNotification']);
    Route::post('/recurring-notification', [PaymentController::class, 'handleRecurringNotification']);
    Route::post('/account-notification', [PaymentController::class, 'handleAccountNotification']);
});

// Public Invitation Data Access
Route::get('/main-infos/{invitationId}', [MainInfoController::class, 'show']);
Route::get('/main-infos/{invitationId}/photo', [MainInfoController::class, 'getPhoto']);
Route::get('/grooms/{invitationId}', [GroomController::class, 'show']);
Route::get('/brides/{invitationId}', [BrideController::class, 'show']);
Route::get('/invitations/{invitationId}/events', [EventController::class, 'getEventsByInvitation']);
Route::get('/invitations/{invitationId}/love-stories', [LoveStoryController::class, 'getStoriesByInvitation']);
Route::get('/invitations/{invitationId}/gift-infos', [GiftInfoController::class, 'getGiftsByInvitation']);
Route::get('/invitations/{invitationId}/galleries', [GalleryController::class, 'show']);
Route::get('/invitations/{slug}/all', [InvitationController::class, 'getInvitationDetailBySlug']);

/*
|--------------------------------------------------------------------------
| Protected API Routes (Authentication Required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (Protected)
    |--------------------------------------------------------------------------
    */
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('users', UserController::class);
    Route::put('/users/admin/{id}', [UserController::class, 'setAdmin']);

    /*
    |--------------------------------------------------------------------------
    | Package Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('packages', PackageController::class)->except(['index', 'show']);
    Route::get('/invitation/{invitationId}/package', [PackageController::class, 'getPackageByInvitationId']);

    /*
    |--------------------------------------------------------------------------
    | Theme Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('themes', ThemeController::class)->except(['index', 'show']);
    Route::post('/themes/orderthemes', [ThemeController::class, 'getThemeByOrderId']);
    Route::get('/invitation/{invitationId}/theme', [ThemeController::class, 'getThemeByInvitationId']);

    /*
    |--------------------------------------------------------------------------
    | Theme Category Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('categories', ThemeCategoryController::class)->except(['index', 'show']);

    /*
    |--------------------------------------------------------------------------
    | Backsound Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('backsounds', BacksoundController::class)->except(['index', 'show']);
    
    /*
    |--------------------------------------------------------------------------
    | Invitation Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('invitations')->group(function () {
        Route::post('/', [InvitationController::class, 'store']);
        Route::get('/user', [InvitationController::class, 'showInvitationByUser']);
        Route::post('/check', [InvitationController::class, 'checkByOrderId']);
        Route::get('/{id}', [InvitationController::class, 'show']);
        Route::put('/{id}', [InvitationController::class, 'update']);
        Route::put('/{id}/complete', [InvitationController::class, 'completeInvitation']);
    });

    /*
    |--------------------------------------------------------------------------
    | Guest Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('guests', GuestController::class)->except(['index', 'show']);

    /*
    |--------------------------------------------------------------------------
    | Payment & Order Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('payments')->group(function () {
        Route::post('/create', [PaymentController::class, 'createPayment']);
        Route::put('/update', [PaymentController::class, 'updatePayment']);
        Route::get('/orders', [OrderController::class, 'getUserOrders']);
        Route::get('/orders/{orderId}', [OrderController::class, 'getOrderStatus']);
        Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancelOrder']);
    });
    
    // Orders
    Route::get('/orders', [OrderController::class, 'getOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/order/{order_id}', [OrderController::class, 'getOrder']);

    /*
    |--------------------------------------------------------------------------
    | Main Info Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('main-infos', MainInfoController::class)->except(['show']);
    Route::post('/main-infos/photo', [MainInfoController::class, 'addOrUpdatePhoto']);

    /*
    |--------------------------------------------------------------------------
    | Groom & Bride Info Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('grooms', GroomController::class)->except(['show']);
    Route::apiResource('brides', BrideController::class)->except(['show']);
    
    /*
    |--------------------------------------------------------------------------
    | Event Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('events', EventController::class);
    Route::delete('events/bulk-delete', [EventController::class, 'bulkDelete']);

    /*
    |--------------------------------------------------------------------------
    | Love Story Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('love-stories', LoveStoryController::class);
    Route::delete('love-stories/bulk-delete', [LoveStoryController::class, 'bulkDelete']);

    /*
    |--------------------------------------------------------------------------
    | Gift Info Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('gift-infos', GiftInfoController::class);
    Route::post('gift-infos/bulk-update', [GiftInfoController::class, 'bulkUpdate']);

    /*
    |--------------------------------------------------------------------------
    | Gallery Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('galleries', GalleryController::class)->except(['show']);
    Route::delete('/invitations/{invitationId}/galleries', [GalleryController::class, 'destroyAll']);
});