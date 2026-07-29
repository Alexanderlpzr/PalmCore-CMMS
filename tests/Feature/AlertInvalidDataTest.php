<?php

use App\Domain\Alerts\Enums\AlertStatus;
use App\Filament\Resources\Alerts\Alert\Pages\ListAlerts;
use App\Filament\Resources\Alerts\Alert\Pages\ViewAlert;
use App\Models\Alert;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Reproduce el 500 reportado al abrir una alerta directo por URL: la columna
// severity/category/status es un string plano sin constraint en la BD, así que
// un valor que no coincide con ningún caso del enum (dato viejo, corrupto, o
// escrito fuera del camino type-safe de AlertService) no debe tumbar la página.
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

function invalidAlert(Tenant $tenant, array $overrides = []): Alert
{
    return Alert::forceCreate(array_merge([
        'tenant_id' => $tenant->id,
        'severity' => 'legacy_old',
        'category' => 'legacy_old',
        'title' => 'Alerta con datos viejos',
        'status' => AlertStatus::Resolved->value,
    ], $overrides));
}

it('resolves an unmapped severity/category value to null instead of throwing', function () {
    $alert = invalidAlert($this->tenant);

    expect($alert->fresh()->severity)->toBeNull()
        ->and($alert->fresh()->category)->toBeNull();
});

it('renders the alert view page without crashing when severity/category are unmapped', function () {
    $alert = invalidAlert($this->tenant);

    Livewire::test(ViewAlert::class, ['record' => $alert->id])
        ->assertOk()
        ->assertSee('Desconocido')
        ->assertSee('Desconocida');
});

it('renders the alerts list without crashing when a row has an unmapped severity', function () {
    invalidAlert($this->tenant, ['status' => AlertStatus::Open->value]);

    Livewire::test(ListAlerts::class)
        ->assertOk()
        ->assertSee('Desconocido');
});
