<?php

use App\Domain\Platform\Enums\HealthStatus;
use App\Domain\Platform\Services\BackupService;
use App\Domain\Platform\Services\PlatformSettingsService;
use App\Domain\Platform\Services\SystemHealthService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Platform\Pages\BackupsPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * El interruptor del respaldo automático.
 *
 * Apagarlo es una decisión legítima. Lo que no es legítimo es que el panel pinte de
 * verde un sistema sin copias solo porque alguien apagó el interruptor: seguiría siendo
 * un riesgo, solo que uno elegido. Y lo que menos se puede permitir es que el sistema
 * deje de respaldar sin que nadie lo haya decidido, que es como estuvo hasta hoy.
 */
beforeEach(function (): void {
    $this->settings = app(PlatformSettingsService::class);
    $this->health = app(SystemHealthService::class);

    $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

    Filament::setCurrentPanel(Filament::getPanel('platform'));
    $this->actingAs($this->superAdmin);

    // Lo que no se está probando aquí, sano: el scheduler late.
    Cache::put('platform.scheduler.heartbeat', now()->toISOString());
});

// ── El interruptor ───────────────────────────────────────────────────────────

it('backs up automatically unless somebody decides otherwise', function (): void {
    // Un sistema que arranca sin respaldos porque nadie fue a activarlos es un sistema
    // sin respaldos. El valor por defecto no es una preferencia: es una red de seguridad.
    expect($this->settings->automaticBackupsEnabled())->toBeTrue();
});

it('remembers who turned it off, and when', function (): void {
    $this->settings->setAutomaticBackups(false, $this->superAdmin);

    $changed = $this->settings->automaticBackupsChangedBy();

    expect($this->settings->automaticBackupsEnabled())->toBeFalse()
        // Un ajuste de plataforma sin autor es una decisión que nadie tomó.
        ->and($changed['user'])->toBe($this->superAdmin->name)
        ->and($changed['at'])->not->toBeNull();
});

it('survives a restart, because it lives in the database and not in the cache', function (): void {
    $this->settings->setAutomaticBackups(false, $this->superAdmin);

    // Un interruptor que decide si hay copias de seguridad no puede olvidarse porque
    // alguien reinició un contenedor: el olvido sería silencioso.
    Cache::flush();

    expect(app(PlatformSettingsService::class)->automaticBackupsEnabled())->toBeFalse();
});

it('stops the nightly backup when it is off', function (): void {
    $nightly = fn (): bool => collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'backup:run'))
        ->contains(fn ($event): bool => $event->filtersPass(app()));

    expect($nightly())->toBeTrue();

    $this->settings->setAutomaticBackups(false, $this->superAdmin);

    // No basta con que el panel diga «desactivado»: la tarea de la 1:00 tiene que dejar
    // de correr de verdad.
    expect($nightly())->toBeFalse();
});

// ── Lo que el panel dice ─────────────────────────────────────────────────────

it('does not paint a system without backups green', function (): void {
    $this->settings->setAutomaticBackups(false, $this->superAdmin);

    $check = collect($this->health->checks())->firstWhere('key', 'backups');

    // Apagado a propósito deja de ser una emergencia, pero sigue siendo un riesgo.
    expect($check['status'])->toBe(HealthStatus::Warning)
        ->and($check['status'])->not->toBe(HealthStatus::Ok)
        ->and($check['value'])->toBe('Desactivados');
});

it('still screams when backups are on but nothing is being saved', function (): void {
    // El automático encendido y el disco vacío significa que la tarea nocturna no corre.
    // Eso sí es una emergencia.
    expect(collect($this->health->checks())->firstWhere('key', 'backups')['status'])
        ->toBe(HealthStatus::Critical);
});

it('lets the super admin flip the switch from the panel', function (): void {
    Livewire::test(BackupsPage::class)
        ->callAction('toggleAutomatic')
        ->assertHasNoActionErrors();

    expect($this->settings->automaticBackupsEnabled())->toBeFalse();

    Livewire::test(BackupsPage::class)
        ->callAction('toggleAutomatic')
        ->assertHasNoActionErrors();

    expect($this->settings->automaticBackupsEnabled())->toBeTrue();
});

it('warns on screen that the copies live on the same server as the database', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put(config('backup.backup.name').'/2026-07-14-01-00-00.zip', 'zip');

    Livewire::test(BackupsPage::class)
        ->assertOk()
        ->assertSee('2026-07-14-01-00-00.zip')
        // Un respaldo que vive junto a la base no protege de perder la máquina.
        ->assertSee('no de perder la máquina');
});

// ── Descargar y borrar ───────────────────────────────────────────────────────

it('refuses to hand over a file that is not a backup', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('secretos/.env', 'APP_KEY=...');

    // Un «descargar» que acepta cualquier ruta es un lector de archivos arbitrarios
    // disfrazado de botón.
    expect(fn () => app(BackupService::class)->pathOf('../secretos/.env'))
        ->toThrow(BusinessRuleException::class);
});

it('deletes a backup only through the list it knows', function (): void {
    Storage::fake('local');
    $path = config('backup.backup.name').'/2026-07-14-01-00-00.zip';
    Storage::disk('local')->put($path, 'zip');

    app(BackupService::class)->delete('2026-07-14-01-00-00.zip');

    expect(Storage::disk('local')->exists($path))->toBeFalse();
});
