<?php

use App\Http\Controllers\Api\V1\ActivationController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FamilyNoteController;
use App\Http\Controllers\Api\V1\GroceryItemController;
use App\Http\Controllers\Api\V1\HouseholdController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MealPlanItemController;
use App\Http\Controllers\Api\V1\MealRequestController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\PermissionRequestController;
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
        Route::patch('households/{household}/members/{member}', [MemberController::class, 'update']);
        Route::post(
            'households/{household}/members/{member}/avatar',
            [MemberController::class, 'updateAvatar'],
        );

        Route::post('households/{household}/invitations', [InvitationController::class, 'store']);
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);

        Route::post(
            'households/{household}/members/{member}/activation-link',
            [ActivationController::class, 'store'],
        );

        Route::get('households/{household}/notes', [FamilyNoteController::class, 'index']);
        Route::post('households/{household}/notes', [FamilyNoteController::class, 'store']);
        Route::delete('households/{household}/notes/{note}', [FamilyNoteController::class, 'destroy']);

        Route::get('households/{household}/announcements', [AnnouncementController::class, 'index']);
        Route::post('households/{household}/announcements', [AnnouncementController::class, 'store']);
        Route::delete(
            'households/{household}/announcements/{announcement}',
            [AnnouncementController::class, 'destroy'],
        );

        Route::get('households/{household}/events', [EventController::class, 'index']);
        Route::post('households/{household}/events', [EventController::class, 'store']);
        Route::put('households/{household}/events/{event}', [EventController::class, 'update']);
        Route::delete('households/{household}/events/{event}', [EventController::class, 'destroy']);

        Route::get('households/{household}/requests', [PermissionRequestController::class, 'index']);
        Route::post('households/{household}/requests', [PermissionRequestController::class, 'store']);
        Route::get(
            'households/{household}/requests/{permissionRequest}',
            [PermissionRequestController::class, 'show'],
        );
        Route::put(
            'households/{household}/requests/{permissionRequest}',
            [PermissionRequestController::class, 'update'],
        );
        Route::post(
            'households/{household}/requests/{permissionRequest}/approve',
            [PermissionRequestController::class, 'approve'],
        );
        Route::post(
            'households/{household}/requests/{permissionRequest}/decline',
            [PermissionRequestController::class, 'decline'],
        );
        Route::post(
            'households/{household}/requests/{permissionRequest}/cancel',
            [PermissionRequestController::class, 'cancel'],
        );
        Route::post(
            'households/{household}/requests/{permissionRequest}/conditions',
            [PermissionRequestController::class, 'addCondition'],
        );
        Route::post(
            'households/{household}/requests/{permissionRequest}/acknowledge',
            [PermissionRequestController::class, 'acknowledge'],
        );

        Route::get('households/{household}/meal-plan-items', [MealPlanItemController::class, 'index']);
        Route::post('households/{household}/meal-plan-items', [MealPlanItemController::class, 'store']);
        Route::delete(
            'households/{household}/meal-plan-items/{mealPlanItem}',
            [MealPlanItemController::class, 'destroy'],
        );

        Route::get('households/{household}/meal-requests', [MealRequestController::class, 'index']);
        Route::post('households/{household}/meal-requests', [MealRequestController::class, 'store']);
        Route::get(
            'households/{household}/meal-requests/{mealRequest}',
            [MealRequestController::class, 'show'],
        );
        Route::put(
            'households/{household}/meal-requests/{mealRequest}',
            [MealRequestController::class, 'update'],
        );
        Route::post(
            'households/{household}/meal-requests/{mealRequest}/approve',
            [MealRequestController::class, 'approve'],
        );
        Route::post(
            'households/{household}/meal-requests/{mealRequest}/decline',
            [MealRequestController::class, 'decline'],
        );
        Route::post(
            'households/{household}/meal-requests/{mealRequest}/cancel',
            [MealRequestController::class, 'cancel'],
        );
        Route::post(
            'households/{household}/meal-requests/{mealRequest}/acknowledge',
            [MealRequestController::class, 'acknowledge'],
        );

        Route::get('households/{household}/grocery-items', [GroceryItemController::class, 'index']);
        Route::post('households/{household}/grocery-items', [GroceryItemController::class, 'store']);
        Route::put(
            'households/{household}/grocery-items/{groceryItem}',
            [GroceryItemController::class, 'update'],
        );
        Route::post(
            'households/{household}/grocery-items/{groceryItem}/purchase',
            [GroceryItemController::class, 'purchase'],
        );
        Route::post(
            'households/{household}/grocery-items/{groceryItem}/unpurchase',
            [GroceryItemController::class, 'unpurchase'],
        );
        Route::delete(
            'households/{household}/grocery-items/{groceryItem}',
            [GroceryItemController::class, 'destroy'],
        );
    });
});
