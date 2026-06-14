<?php

use App\Console\Commands\SendOverdueMaintenanceNotificationsCommand;
use App\Domain\Reports\Services\ReportManager;
use App\Jobs\EvaluateAutomationRulesJob;
use App\Jobs\RecalculateAllEquipmentKpisJob;
use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RecalculateAllEquipmentKpisJob)
    ->dailyAt('02:00')
    ->onOneServer();

Schedule::call(fn () => ReportManager::cleanupOldReports())
    ->name('reports:cleanup')
    ->dailyAt('03:00')
    ->onOneServer();

Schedule::command(SendOverdueMaintenanceNotificationsCommand::class)
    ->dailyAt('08:00')
    ->onOneServer();

// Dispatch one EvaluateAutomationRulesJob per active tenant every hour.
// ShouldBeUnique on the job prevents concurrent evaluation per tenant.
Schedule::call(function (): void {
    Tenant::withoutGlobalScopes()
        ->where('is_active', true)
        ->select('id')
        ->each(fn (Tenant $tenant) => EvaluateAutomationRulesJob::dispatch($tenant->id));
})
    ->name('automations:evaluate')
    ->hourly()
    ->onOneServer();

// Horizon metrics snapshots — required for dashboard graphs to populate
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes();

// Daily database backup at 01:00, cleanup at 01:30
Schedule::command('backup:run --only-db')
    ->dailyAt('01:00')
    ->onOneServer()
    ->runInBackground();

Schedule::command('backup:clean')
    ->dailyAt('01:30')
    ->onOneServer();
