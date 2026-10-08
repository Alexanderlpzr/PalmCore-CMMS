<?php

use App\Domain\HumanResources\Services\AttendanceService;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\Porteria;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * La puerta desde el panel web: el vigilante con un computador, una tableta o un lector
 * USB. Marca lo mismo que la app móvil.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Fermín', 'last_name' => 'Beltrán']);
    $this->card = EmployeeQrCode::factory()->forEmployee($this->employee)->create();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function entrarALaPuertaComo(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    test()->actingAs($user);
    Filament::setTenant($tenant);

    return $user;
}

it('marks the entry of a badge read by the camera and shows who it was', function (): void {
    $vigilante = entrarALaPuertaComo($this->tenant, 'porteria');

    Livewire::test(Porteria::class)
        ->call('registrarMarca', $this->card->qr_token)
        ->assertSee('Fermín Beltrán')
        ->assertSee('Entrada')
        ->assertSet('error', null);

    $marca = AttendanceScan::query()->sole();

    expect($marca->recorded_by)->toBe($vigilante->id)
        ->and($marca->gate)->toBe('Portería (panel)');
});

it('marks from the field, the way a USB reader types the code and presses Enter', function (): void {
    entrarALaPuertaComo($this->tenant, 'porteria');

    Livewire::test(Porteria::class)
        ->set('token', $this->card->qr_token)
        ->call('marcarDelCampo')
        ->assertSet('token', '')
        ->assertSee('Fermín Beltrán');

    expect(AttendanceScan::query()->count())->toBe(1);
});

it('says why a code does not mark', function (): void {
    entrarALaPuertaComo($this->tenant, 'porteria');

    Livewire::test(Porteria::class)
        ->call('registrarMarca', 'no-es-un-carne')
        ->assertSet('error', 'Ese no es el código de un carné.')
        ->call('registrarMarca', '1b4e28ba-2fa1-4d3b-a3f5-ef19b5a7633b')
        ->assertSet('error', 'Ese carné no corresponde a ningún trabajador activo.');

    expect(AttendanceScan::query()->count())->toBe(0);
});

it('counts who is inside right now', function (): void {
    entrarALaPuertaComo($this->tenant, 'porteria');
    $otro = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $carneDelOtro = EmployeeQrCode::factory()->forEmployee($otro)->create();

    app(AttendanceService::class)->record($this->card, at: now()->subHours(3));
    app(AttendanceService::class)->record($carneDelOtro, at: now()->subHours(3));
    app(AttendanceService::class)->record($carneDelOtro, at: now()->subHour());

    Livewire::test(Porteria::class)->assertViewHas('adentro', 1);
});

it('is the guard\'s screen, not human resources\' or maintenance\'s', function (): void {
    entrarALaPuertaComo($this->tenant, 'porteria');
    expect(Porteria::canAccess())->toBeTrue();

    entrarALaPuertaComo($this->tenant, 'talento-humano');
    expect(Porteria::canAccess())->toBeFalse();

    entrarALaPuertaComo($this->tenant, 'administrador-general');
    expect(Porteria::canAccess())->toBeFalse();
});

it('takes the guard straight to the gate when opening the panel', function (): void {
    entrarALaPuertaComo($this->tenant, 'porteria');

    Livewire::test(Inicio::class)->assertRedirect(Porteria::getUrl());
});

it('tells the guard a second pass within two minutes did not mark', function (): void {
    entrarALaPuertaComo($this->tenant, 'porteria');
    $this->travelTo(now()->setTimezone('America/Bogota')->setTime(7, 0));

    Livewire::test(Porteria::class)
        ->call('registrarMarca', $this->card->qr_token)
        ->assertSet('ultimo.repetido', false)
        ->call('registrarMarca', $this->card->qr_token)
        ->assertSet('ultimo.repetido', true)
        ->assertDispatched('porteria-marca', ok: false)
        ->assertSee('Ya marcó Entrada')
        ->assertSee('Podrá volver a marcar desde las 07:02 am');

    expect(AttendanceScan::query()->count())->toBe(1);
});

it('shows the result over the camera, beeps, and keeps the last good mark after an error', function (): void {
    entrarALaPuertaComo($this->tenant, 'porteria');

    Livewire::test(Porteria::class)
        ->call('registrarMarca', $this->card->qr_token)
        ->assertDispatched('porteria-marca', ok: true)
        ->assertSet('intento', 1)
        ->assertSee('wire:key="resultado-1"', escape: false)
        ->call('registrarMarca', 'no-es-un-carne')
        ->assertDispatched('porteria-marca', ok: false)
        ->assertSet('intento', 2)
        ->assertSee('No se marcó')
        // La franja de abajo sigue diciendo quién marcó bien la última vez.
        ->assertSet('ultimaBuena.nombre', 'Fermín Beltrán')
        ->assertSee('Última:');
});
