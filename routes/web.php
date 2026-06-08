<?php

use App\Http\Controllers\EquipmentPublicController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/equipment/qr/{token}', [EquipmentPublicController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('equipment.qr.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
