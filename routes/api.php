<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DraftController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GoogleSheetsController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MayarController;
use App\Http\Controllers\Api\OpsBackupController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductBatchController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicTenantController;
use App\Http\Controllers\Api\ReceivablesController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SheetsSyncController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TenantExportController;
use App\Http\Controllers\Api\TenantSettingsController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::get('/public/tenant', [PublicTenantController::class, 'current']);

Route::post('/ops/backup', [OpsBackupController::class, 'store'])
    ->middleware(['n8n.backup', 'throttle:service-webhook']);

Route::post('/webhooks/mayar/{tenantSlug}', [MayarController::class, 'webhook'])
    ->middleware(['mayar.webhook', 'throttle:service-webhook']);

Route::get('/integrations/google/callback', [GoogleSheetsController::class, 'callback'])
    ->middleware('throttle:api');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

Route::middleware(['auth:sanctum', 'tenant', 'tenant.active', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/settings/tenant', [TenantSettingsController::class, 'show']);
    Route::get('/settings/app', [TenantSettingsController::class, 'app']);
    Route::patch('/settings/tenant', [TenantSettingsController::class, 'update']);
    Route::patch('/settings/tenant/appearance', [TenantSettingsController::class, 'updateAppearance']);
    Route::post('/settings/tenant/logo', [TenantSettingsController::class, 'uploadLogo']);
    Route::delete('/settings/tenant/logo', [TenantSettingsController::class, 'deleteLogo']);

    Route::middleware('role:owner')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/password/email', [AuthController::class, 'sendResetLink'])->middleware('throttle:password-reset');
        Route::get('/settings/ppn', [SettingsController::class, 'ppn']);
        Route::patch('/settings/ppn', [SettingsController::class, 'updatePpn']);
        Route::get('/settings/pajak', [SettingsController::class, 'pajak']);
        Route::patch('/settings/pajak', [SettingsController::class, 'updatePajak']);
        Route::get('/settings/delivery', [SettingsController::class, 'delivery']);
        Route::patch('/settings/delivery', [SettingsController::class, 'updateDelivery']);
        Route::get('/settings/integrations', [SettingsController::class, 'integrations']);
        Route::patch('/settings/integrations', [SettingsController::class, 'updateIntegrations']);
        Route::get('/settings/backup', [SettingsController::class, 'backup']);
        Route::get('/settings/payment', [SettingsController::class, 'payment']);
        Route::patch('/settings/payment', [SettingsController::class, 'updatePayment']);
        Route::post('/settings/payment/qris', [SettingsController::class, 'uploadQris']);
        Route::delete('/settings/payment/qris', [SettingsController::class, 'deleteQris']);
        Route::get('/settings/mayar/test', [SettingsController::class, 'testMayar']);
        Route::post('/settings/delivery/test', [DeliveryController::class, 'testConnection']);
        Route::get('/tenant/export', [TenantExportController::class, 'export']);
        Route::post('/orders/reassign', [OrderController::class, 'reassign']);
        Route::prefix('reports')->group(function () {
            Route::get('/daily', [ReportController::class, 'daily']);
            Route::get('/daily-trend', [ReportController::class, 'dailyTrend']);
            Route::get('/staff', [ReportController::class, 'staff']);
            Route::get('/product', [ReportController::class, 'product']);
            Route::get('/status', [ReportController::class, 'status']);
            Route::get('/tax', [ReportController::class, 'tax']);
            Route::get('/efaktur', [ReportController::class, 'efaktur']);
            Route::get('/print', [ReportController::class, 'print']);
        });
        Route::get('/sheets-sync', [SheetsSyncController::class, 'index']);
        Route::post('/sheets-sync/dispatch', [SheetsSyncController::class, 'dispatch']);
        Route::post('/sheets-sync/{sheetsSync}/retry', [SheetsSyncController::class, 'retry']);
        Route::get('/integrations/google/connect', [GoogleSheetsController::class, 'connect']);
        Route::delete('/integrations/google', [GoogleSheetsController::class, 'disconnect']);
        Route::post('/integrations/google/import', [GoogleSheetsController::class, 'import']);
        Route::post('/imports', [ImportController::class, 'store']);
        Route::get('/imports/template', [ImportController::class, 'template']);
        Route::get('/inventory/alerts', [InventoryController::class, 'alerts']);
        Route::get('/inventory/expiry-alerts', [InventoryController::class, 'expiryAlerts']);
        Route::post('/inventory/opname', [InventoryController::class, 'opname']);
        Route::get('/inventory/movements', [InventoryController::class, 'movements']);
        Route::post('/inventory/receipts', [InventoryController::class, 'receipts']);
        Route::post('/product-units/{productUnit}/restock', [InventoryController::class, 'restock']);
        Route::get('/products/lookup', [InventoryController::class, 'lookup']);
        Route::get('/product-batches', [ProductBatchController::class, 'index']);
        Route::post('/product-batches/{productBatch}/write-off', [ProductBatchController::class, 'writeOff']);
        Route::get('/settings/expiry', [SettingsController::class, 'expiry']);
        Route::patch('/settings/expiry', [SettingsController::class, 'updateExpiry']);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('customers', CustomerController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('expenses', ExpenseController::class)->except(['show']);
    });

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('customers', CustomerController::class)->only(['index', 'show']);
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/photo', [ProductController::class, 'uploadPhoto']);
    Route::delete('/products/{product}/photo', [ProductController::class, 'deletePhoto']);
    Route::get('/orders/summary', [OrderController::class, 'summary']);
    Route::get('/receivables', [ReceivablesController::class, 'index']);
    Route::apiResource('orders', OrderController::class);
    Route::get('/orders/{order}/activity', [ActivityLogController::class, 'forOrder']);
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'show']);
    Route::get('/orders/{order}/invoice/preview', [InvoiceController::class, 'preview']);

    Route::get('/orders/{order}/payments', [PaymentController::class, 'index']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);
    Route::delete('/orders/{order}/payments/{payment}', [PaymentController::class, 'destroy']);

    Route::post('/orders/{order}/returns', [ReturnController::class, 'store']);

    Route::post('/orders/{order}/mayar/link', [MayarController::class, 'link']);

    Route::post('/delivery/estimate', [DeliveryController::class, 'estimate'])->middleware('throttle:delivery');
    Route::post('/delivery/reverse', [DeliveryController::class, 'reverse'])->middleware('throttle:delivery');
    Route::get('/delivery/settings', [DeliveryController::class, 'settings']);

    Route::post('/drafts', [DraftController::class, 'store']);
    Route::get('/drafts/restore', [DraftController::class, 'restore']);
    Route::delete('/drafts', [DraftController::class, 'destroy']);
});

Route::any('{path}', fn () => response()->json(['message' => 'Not found.'], 404))
    ->where('path', '.*')
    ->fallback();
