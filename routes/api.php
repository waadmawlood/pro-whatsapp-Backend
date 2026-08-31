<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MediaFileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WhatsAppAccountController;
use App\Http\Controllers\Api\V1\WhatsAppBridgeWebhookController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('media-files/{mediaFile}', [MediaFileController::class, 'show'])
        ->middleware('signed')
        ->name('api.v1.media-files.show');

    Route::get('webhooks/whatsapp/{whatsappAccount}', [WhatsAppWebhookController::class, 'verify']);
    Route::post('webhooks/whatsapp/{whatsappAccount}', [WhatsAppWebhookController::class, 'receive'])
        ->middleware('throttle:webhooks');

    Route::prefix('webhooks/whatsapp-bridge/{whatsappAccount}')
        ->middleware(['bridge.secret', 'throttle:webhooks'])
        ->group(function (): void {
            Route::post('connection', [WhatsAppBridgeWebhookController::class, 'connection']);
            Route::post('message', [WhatsAppBridgeWebhookController::class, 'message']);
            Route::post('status', [WhatsAppBridgeWebhookController::class, 'status']);
        });

    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active', 'company'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::get('auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('auth/sessions/{token}', [AuthController::class, 'revokeSession']);

        Route::get('dashboard', DashboardController::class);
        Route::get('search', SearchController::class);
        Route::get('permissions', PermissionController::class);
        Route::get('audit-logs', AuditLogController::class);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::apiResource('users', UserController::class);
        Route::apiResource('customers', CustomerController::class);
        Route::get('customers/{customer}/notes', [CustomerController::class, 'notes']);
        Route::post('customers/{customer}/notes', [CustomerController::class, 'storeNote']);
        Route::post('customers/{customer}/tags', [CustomerController::class, 'syncTags']);

        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
        Route::patch('conversations/{conversation}', [ConversationController::class, 'update']);
        Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy']);
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);
        Route::post('conversations/{conversation}/assign', [ConversationController::class, 'assign']);
        Route::post('conversations/{conversation}/close', [ConversationController::class, 'close']);
        Route::post('conversations/{conversation}/reopen', [ConversationController::class, 'reopen']);

        Route::apiResource('tags', TagController::class)->except(['show']);
        Route::apiResource('whatsapp-accounts', WhatsAppAccountController::class)
            ->parameters(['whatsapp-accounts' => 'whatsappAccount']);
        Route::get('whatsapp-accounts/{whatsappAccount}/bridge', [WhatsAppAccountController::class, 'bridgeState']);
        Route::get('whatsapp-accounts/{whatsappAccount}/bridge/status', [WhatsAppAccountController::class, 'bridgeStatus']);
        Route::get('whatsapp-accounts/{whatsappAccount}/bridge/qr', [WhatsAppAccountController::class, 'bridgeQr']);
        Route::post('whatsapp-accounts/{whatsappAccount}/bridge/connect', [WhatsAppAccountController::class, 'bridgeConnect']);
        Route::post('whatsapp-accounts/{whatsappAccount}/bridge/disconnect', [WhatsAppAccountController::class, 'bridgeDisconnect']);
    });
});
