<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhooks\SquareWebhookController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\API\AdminPushNotificationController;
use App\Http\Controllers\API\UserPushNotificationController;
use App\Http\Controllers\API\CustomGlobalAPIController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// Webhooks (no auth required)
Route::post('/webhooks/square', [SquareWebhookController::class, 'handle']);

// Public payment config (no auth; publishable-only values, no secrets)
Route::get('/payment-config', [\App\Http\Controllers\API\PaymentConfigController::class, 'show']);

// Notification Preferences (authenticated routes)
Route::middleware('auth:api')->group(function () {
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::get('notification-preferences/{category}', [NotificationPreferenceController::class, 'show']);
    Route::put('notification-preferences/{category}', [NotificationPreferenceController::class, 'update']);
});

// Admin Push Notifications API (authenticated routes)
Route::middleware('auth:api')->prefix('admin/push-notifications')->controller(AdminPushNotificationController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::put('/{id}', 'update');
    Route::post('/{id}/send', 'send');
    Route::delete('/{id}', 'destroy');
});

// User Push Notifications Management API (authenticated routes)
Route::middleware('auth:api')->prefix('user')->controller(UserPushNotificationController::class)->group(function () {
    Route::get('/push-notifications', 'getPreferences');
    Route::post('/push-notification/{category}/toggle', 'togglePush');
    Route::post('/email-notification/{category}/toggle', 'toggleEmail');
});

// Include Core API routes
require __DIR__.'/core_api.php';


// Custom API With No Auth
Route::prefix('custom')->controller(CustomGlobalAPIController::class)->group(function () {
    Route::get('/get-order/{order_number}/info', 'getOrderFullInfo');
});

