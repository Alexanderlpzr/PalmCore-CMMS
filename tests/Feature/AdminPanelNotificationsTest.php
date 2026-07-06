<?php

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

// Regression: export jobs (Excel, Inventory PDF, etc.) call ->sendToDatabase()
// to notify the user their download is ready, but the panel never called
// ->databaseNotifications() — so those notifications were stored correctly
// with zero UI to ever see them (no bell icon, no polling). Users saw the
// initial "Generando..." toast and then nothing, forever, even though the
// file existed and the notification was sitting in the database.
it('the admin panel has database notifications enabled', function () {
    expect(Filament::getPanel('admin')->hasDatabaseNotifications())->toBeTrue();
});

// Regression: enabling the bell made Filament's topbar count unread rows with
// `data->>'format' = 'filament'`, which PostgreSQL rejects on a text column
// ("operator does not exist: text ->> unknown"). The notifications.data column
// must be json/jsonb for this query to run.
it('counts filament database notifications without a postgres json-operator error', function () {
    $user = User::factory()->create();

    Notification::make()
        ->title('Reporte listo')
        ->sendToDatabase($user);

    $count = $user->unreadNotifications()
        ->where('data->format', 'filament')
        ->count();

    expect($count)->toBe(1);
});
