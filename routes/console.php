<?php

use App\Console\Commands\SendOverdueMaintenanceNotificationsCommand;
use App\Domain\Reports\Services\ReportManager;
use App\Jobs\DetectStaleMeterReadingsJob;
use App\Jobs\EvaluateAutomationRulesJob;
use App\Jobs\GeneratePreventiveWorkOrdersJob;
use App\Jobs\RecalculateAllEquipmentKpisJob;
use App\Jobs\SnapshotPlantKpisJob;
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

// A7 — los horómetros mudos, antes de generar los preventivos: un plan por horas
// que no recibe lecturas deja de generar OTs en silencio, y el silencio es el
// problema. Se avisa la misma mañana, no el día que la máquina se rompe.
Schedule::call(function (): void {
    Tenant::withoutGlobalScopes()
        ->where('is_active', true)
        ->select('id')
        ->each(fn (Tenant $tenant) => DetectStaleMeterReadingsJob::dispatch($tenant->id));
})
    ->name('maintenance:detect-stale-meters')
    ->dailyAt('04:30')
    ->onOneServer();

// Generate the preventive work orders due in the next 7 days, one job per tenant.
// The generator skips plans that already have an open OT, so this is safe to
// re-run; it is what turns the maintenance plans into actual work.
Schedule::call(function (): void {
    Tenant::withoutGlobalScopes()
        ->where('is_active', true)
        ->select('id')
        ->each(fn (Tenant $tenant) => GeneratePreventiveWorkOrdersJob::dispatch($tenant->id));
})
    ->name('maintenance:generate-preventives')
    ->dailyAt('05:00')
    ->onOneServer();

// Close the month: freeze each plant's efficiency, MTBF and MTTR on the 1st.
Schedule::job(new SnapshotPlantKpisJob)
    ->monthlyOn(1, '04:00')
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
