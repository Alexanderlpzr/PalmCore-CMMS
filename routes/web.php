<?php

use App\Http\Controllers\Admin\AuditLogExportController;
use App\Http\Controllers\Api\V1\ImpersonationStatusController;
use App\Http\Controllers\EquipmentPublicController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\PlatformHealthController;
use App\Http\Controllers\ReportDownloadController;
use Illuminate\Support\Facades\Route;

// Send the root URL straight to the admin panel. Filament's auth middleware
// bounces unauthenticated visitors to /admin/login and authenticated ones to
// the dashboard — so no one ever lands on the default Laravel welcome page.
Route::redirect('/', '/admin')->name('home');

// ¿Respira la aplicación? Lo que Railway consulta para decidir si reinicia el contenedor.
Route::get('/health', HealthCheckController::class)
    ->middleware('throttle:30,1')
    ->name('health');

// ¿Funciona la plataforma? Scheduler, respaldos, colas sin worker. Reiniciar el
// contenedor no arregla nada de esto, así que tiene su propia puerta y su propio 503.
Route::get('/health/platform', PlatformHealthController::class)
    ->middleware('throttle:30,1')
    ->name('health.platform');

Route::get('/equipment/qr/{token}', [EquipmentPublicController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('equipment.qr.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Super Admin impersonation (session-based, CSRF-protected via the web group).
// Authorization is enforced inside ImpersonationService.
Route::middleware('auth')->group(function () {
    // 'leave' is declared first so it is not captured by the {user} wildcard.
    Route::post('/impersonation/leave', [ImpersonationController::class, 'leave'])
        ->name('impersonation.leave');
    Route::post('/impersonation/{user}', [ImpersonationController::class, 'start'])
        ->name('impersonation.start');
});

Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/reports/download', [ReportDownloadController::class, 'download'])
        ->name('reports.download');
});

Route::middleware(['auth', 'super-admin'])->group(function () {
    Route::get('/admin/audit-logs/export', [AuditLogExportController::class, 'export'])
        ->name('admin.audit-logs.export');
});

// Impersonation status for the SPA — served via web routes to have session access.
// Credentials-include fetches from the SPA will include the Laravel session cookie.
Route::get('/api/v1/impersonation/status', ImpersonationStatusController::class)
    ->middleware('throttle:60,1')
    ->name('api.v1.impersonation.status');

require __DIR__.'/settings.php';

// Operations Panel SPA — Vue 3 desktop/mobile app
Route::get('/app/{any?}', fn () => view('ops.app'))
    ->where('any', '.*')
    ->name('ops.app');

// Mobile PWA catch-all — must remain last so it doesn't shadow specific routes
Route::view('/mobile/{any}', 'mobile.app')
    ->where('any', '.*')
    ->name('mobile.app');
