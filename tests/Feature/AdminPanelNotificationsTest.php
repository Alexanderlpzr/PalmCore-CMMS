<?php

use Filament\Facades\Filament;

// Regression: export jobs (Excel, Inventory PDF, etc.) call ->sendToDatabase()
// to notify the user their download is ready, but the panel never called
// ->databaseNotifications() — so those notifications were stored correctly
// with zero UI to ever see them (no bell icon, no polling). Users saw the
// initial "Generando..." toast and then nothing, forever, even though the
// file existed and the notification was sitting in the database.
it('the admin panel has database notifications enabled', function () {
    expect(Filament::getPanel('admin')->hasDatabaseNotifications())->toBeTrue();
});
