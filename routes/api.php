<?php

use App\Http\Controllers\Api\V1\ActivationController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\HouseholdController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::patch('me', [AuthController::class, 'updateProfile']);
        });
    });

    // Public — reached by someone who may not have an account yet.
    Route::get('invitations/{token}', [InvitationController::class, 'show']);
    Route::get('activation/{token}', [ActivationController::class, 'show']);
    Route::post('activation/{token}/claim', [ActivationController::class, 'claim']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('households', HouseholdController::class)
            ->only(['index', 'store', 'show', 'update']);

        Route::get('households/{household}/members', [MemberController::class, 'index']);
        Route::post('households/{household}/members', [MemberController::class, 'store']);

        Route::post('households/{household}/invitations', [InvitationController::class, 'store']);
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);

        Route::post(
            'households/{household}/members/{member}/activation-link',
            [ActivationController::class, 'store'],
        );
    });
});
