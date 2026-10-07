<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Resources\PayrollRuns\Pages\CreatePayrollRun;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\PayrollRun;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Al crear la nómina, la ventana de horas sale sola del día de corte de la empresa y se
 * mueve con el período: la de noviembre es del 27 de octubre al 26 de noviembre.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));

    $rrhh = User::factory()->create(['is_active' => true]);
    $rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $rrhh->assignRole('talento-humano');
    $this->actingAs($rrhh);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
    // En una petición lo fija el middleware del panel; en la prueba, a mano.
    CurrentTenant::set($this->tenant);
});

it('proposes the hours window from the cutoff day and moves it with the period', function (): void {
    app(PayrollParameterService::class)->setValue(PayrollParameter::HoursCutoffDay, 26, Carbon::parse('2026-01-01'), $this->tenant->id);

    Livewire::test(CreatePayrollRun::class)
        ->fillForm(['name' => 'Nómina de noviembre de 2026', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30'])
        ->assertSchemaStateSet(['hours_from' => '2026-10-27', 'hours_to' => '2026-11-26'])
        ->call('create')
        ->assertHasNoFormErrors();

    $run = PayrollRun::query()->forTenant($this->tenant->id)->sole();

    expect($run->hours_from->toDateString())->toBe('2026-10-27')
        ->and($run->hours_to->toDateString())->toBe('2026-11-26')
        ->and($run->hasHoursCutoff())->toBeTrue();
});

it('leaves the window empty for a company that pays by calendar month', function (): void {
    Livewire::test(CreatePayrollRun::class)
        ->fillForm(['name' => 'Nómina de noviembre de 2026', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30'])
        ->assertSchemaStateSet(['hours_from' => null, 'hours_to' => null])
        ->call('create')
        ->assertHasNoFormErrors();

    $run = PayrollRun::query()->forTenant($this->tenant->id)->sole();

    expect($run->hours_from)->toBeNull()
        ->and($run->hasHoursCutoff())->toBeFalse()
        ->and($run->hoursFrom()->toDateString())->toBe('2026-11-01');
});
