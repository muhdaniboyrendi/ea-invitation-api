<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InvitationController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('invitations', InvitationController::class);
});

Route::apiResource('invitations', InvitationController::class);