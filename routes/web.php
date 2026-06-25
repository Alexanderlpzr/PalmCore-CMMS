<?php

use App\Http\Controllers\EquipmentPublicController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\ReportDownloadController;
use Illuminate\Support\Facades\Route;

// Send the root URL straight to the admin panel. Filament's auth middleware
// bounces unauthenticated visitors to /admin/login and authenticated ones to
// the dashboard — so no one ever lands on the default Laravel welcome page.
Route::redirect('/', '/admin')->name('home');

Route::get('/health', HealthCheckController::class)
    ->middleware('throttle:30,1')
    ->name('health');

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

require __DIR__.'/settings.php';

// Operations Panel SPA — Vue 3 desktop/mobile app
Route::get('/app/{any?}', fn () => view('ops.app'))
    ->where('any', '.*')
    ->name('ops.app');

// Mobile PWA catch-all — must remain last so it doesn't shadow specific routes
Route::view('/mobile/{any}', 'mobile.app')
    ->where('any', '.*')
    ->name('mobile.app');
