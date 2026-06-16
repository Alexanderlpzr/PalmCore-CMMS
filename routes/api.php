<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\ApiTokenController;
use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\DowntimeEventController;
use App\Http\Controllers\Api\V1\EquipmentActivityController;
use App\Http\Controllers\Api\V1\EquipmentCategoryController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\EquipmentKpiController;
use App\Http\Controllers\Api\V1\InventoryTransactionController;
use App\Http\Controllers\Api\V1\MaintenancePlanController;
use App\Http\Controllers\Api\V1\MaintenanceRequestController;
use App\Http\Controllers\Api\V1\PlantController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SparePartController;
use App\Http\Controllers\Api\V1\TokenRefreshController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\WorkOrderCommentController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use App\Http\Controllers\Api\V1\WorkOrderMediaController;
use App\Http\Controllers\Api\V1\WorkOrderSignatureController;
use App\Http\Controllers\Api\V1\WorkOrderTimeEntryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ── Auth: token management & refresh ─────────────────────────────────────
    Route::post('/tokens', [ApiTokenController::class, 'store'])
        ->middleware('throttle:api-tokens')
        ->name('api.v1.tokens.store');

    // Refresh: uses HttpOnly cookie — no auth:sanctum required
    Route::post('/auth/refresh', [TokenRefreshController::class, 'store'])
        ->middleware('throttle:api-tokens')
        ->name('api.v1.auth.refresh');

    // Logout: works even if access token expired
    Route::delete('/auth/logout', [TokenRefreshController::class, 'destroy'])
        ->name('api.v1.auth.logout');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/tokens', [ApiTokenController::class, 'index'])
            ->name('api.v1.tokens.index');

        Route::delete('/tokens/{token}', [ApiTokenController::class, 'destroy'])
            ->name('api.v1.tokens.destroy');
    });

    // ── Protected resource routes ─────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'api.tenant', 'throttle:api'])->group(function () {
        // Global command-palette search across resources
        Route::get('search', [SearchController::class, 'index'])->name('api.v1.search');

        // Equipment — by-qr must be registered before apiResource to avoid {id} catch
        Route::get('equipment/by-qr/{qr_token}', [EquipmentController::class, 'byQrToken'])
            ->name('api.v1.equipment.by-qr');
        Route::get('equipment/{id}/activity', [EquipmentActivityController::class, 'index'])
            ->name('api.v1.equipment.activity');
        Route::patch('equipment/bulk', [EquipmentController::class, 'bulk'])
            ->name('api.v1.equipment.bulk');
        Route::apiResource('equipment', EquipmentController::class)->only(['index', 'show']);
        Route::post('equipment', [EquipmentController::class, 'store'])
            ->middleware('idempotency')
            ->name('api.v1.equipment.store');

        Route::get('equipment-categories', [EquipmentCategoryController::class, 'index'])
            ->name('api.v1.equipment-categories.index');

        // Work Orders — mine must be registered before apiResource to avoid {id} catch
        Route::get('work-orders/mine', [WorkOrderController::class, 'mine'])
            ->name('api.v1.work-orders.mine');
        Route::patch('work-orders/bulk', [WorkOrderController::class, 'bulk'])
            ->name('api.v1.work-orders.bulk');
        Route::apiResource('work-orders', WorkOrderController::class)->only(['index', 'show']);
        Route::post('work-orders', [WorkOrderController::class, 'store'])
            ->middleware('idempotency')
            ->name('work-orders.store');
        Route::patch('work-orders/{id}/status', [WorkOrderController::class, 'updateStatus'])
            ->name('api.v1.work-orders.update-status');

        // Work Order sub-resources (Sprint 10.0 mobile endpoints)
        Route::post('work-orders/{workOrder}/time-entries', [WorkOrderTimeEntryController::class, 'store'])
            ->name('api.v1.work-orders.time-entries.store');
        Route::post('work-orders/{workOrder}/comments', [WorkOrderCommentController::class, 'store'])
            ->name('api.v1.work-orders.comments.store');
        Route::post('work-orders/{workOrder}/media', [WorkOrderMediaController::class, 'store'])
            ->name('api.v1.work-orders.media.store');
        Route::post('work-orders/{workOrder}/signature', [WorkOrderSignatureController::class, 'store'])
            ->name('api.v1.work-orders.signature.store');

        Route::apiResource('maintenance-plans', MaintenancePlanController::class)->only(['index', 'show']);

        Route::patch('maintenance-requests/bulk', [MaintenanceRequestController::class, 'bulk'])
            ->name('api.v1.maintenance-requests.bulk');
        Route::apiResource('maintenance-requests', MaintenanceRequestController::class)->only(['index', 'show']);
        Route::post('maintenance-requests', [MaintenanceRequestController::class, 'store'])
            ->middleware('idempotency')
            ->name('maintenance-requests.store');
        Route::patch('maintenance-requests/{id}/status', [MaintenanceRequestController::class, 'updateStatus'])
            ->name('api.v1.maintenance-requests.update-status');

        Route::post('inventory/transactions', [InventoryTransactionController::class, 'store'])
            ->middleware('idempotency')
            ->name('api.v1.inventory.transactions.store');

        Route::apiResource('inventory/spare-parts', SparePartController::class)->only(['index', 'show']);
        Route::apiResource('inventory/warehouses', WarehouseController::class)->only(['index', 'show']);
        Route::apiResource('downtime-events', DowntimeEventController::class)->only(['index', 'show']);
        Route::apiResource('plants', PlantController::class)->only(['index', 'show']);
        Route::apiResource('areas', AreaController::class)->only(['index', 'show']);

        // Alerts — Sprint 11.1 (read) + Sprint 11.2 (actions)
        // count must be registered before {alert} to avoid being caught as a route parameter
        Route::get('alerts', [AlertController::class, 'index'])->name('api.v1.alerts.index');
        Route::get('alerts/count', [AlertController::class, 'count'])->name('api.v1.alerts.count');
        Route::patch('alerts/{id}/resolve', [AlertController::class, 'resolve'])->name('api.v1.alerts.resolve');
        Route::patch('alerts/{id}/dismiss', [AlertController::class, 'dismiss'])->name('api.v1.alerts.dismiss');

        // Push notifications — user preference routes (no specific ability required)
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])
            ->name('api.v1.push-subscriptions.store');
        Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])
            ->name('api.v1.push-subscriptions.destroy');
    });

    // Heavy endpoints: reliability KPIs carry more DB weight; PDF rendering is CPU-heavy
    Route::middleware(['auth:sanctum', 'api.tenant', 'throttle:api-heavy'])->group(function () {
        Route::apiResource('reliability/kpis', EquipmentKpiController::class)->only(['index', 'show']);

        // On-demand PDF reports (DomPDF) — streamed to the token-authenticated SPA
        Route::get('reports/reliability', [ReportController::class, 'reliability'])->name('api.v1.reports.reliability');
        Route::get('reports/inventory', [ReportController::class, 'inventory'])->name('api.v1.reports.inventory');
        Route::get('reports/work-orders/{id}', [ReportController::class, 'workOrder'])->name('api.v1.reports.work-order');
        Route::get('reports/equipment/{id}', [ReportController::class, 'equipment'])->name('api.v1.reports.equipment');
        Route::get('reports/maintenance-plans/{id}', [ReportController::class, 'maintenancePlan'])->name('api.v1.reports.maintenance-plan');
    });
});
