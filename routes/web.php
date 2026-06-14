<?php

use App\Http\Controllers\EquipmentPublicController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ReportDownloadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/health', HealthCheckController::class)
    ->middleware('throttle:30,1')
    ->name('health');

Route::get('/equipment/qr/{token}', [EquipmentPublicController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('equipment.qr.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/reports/download', [ReportDownloadController::class, 'download'])
        ->name('reports.download');
});

require __DIR__.'/settings.php';

// Mobile PWA catch-all — must remain last so it doesn't shadow specific routes
Route::view('/mobile/{any}', 'mobile.app')
    ->where('any', '.*')
    ->name('mobile.app');
