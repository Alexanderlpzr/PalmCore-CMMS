<?php

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Pages\RevisarHoras;
use App\Filament\Resources\AttendanceDays\Pages\ListAttendanceDays;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Revisar la semana: la cuadrícula de trabajadores por días, el panel del día y el botón
 * que confirma de una vez lo que no tiene avisos. Hoy es el miércoles 14 de octubre.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);
    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));
    $this->travelTo(Carbon::parse('2026-10-14 10:00', 'America/Bogota'));

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ana', 'last_name' => 'Ruiz']);
    $carne = EmployeeQrCode::factory()->forEmployee($this->ana)->create();

    // Lunes 12: un turno limpio. Martes 13: entró y nadie marcó la salida (con aviso).
    foreach (['2026-10-12 06:00', '2026-10-12 14:00', '2026-10-13 06:00'] as $hora) {
        app(AttendanceService::class)->record($carne, at: Carbon::parse($hora, 'America/Bogota')->utc());
    }
    app(AttendanceDayBuilder::class)->buildForEmployee($this->ana, Carbon::parse('2026-10-12'), Carbon::parse('2026-10-14'));

    $this->limpio = AttendanceDay::query()->whereDate('work_date', '2026-10-12')->sole();
    $this->conAviso = AttendanceDay::query()->whereDate('work_date', '2026-10-13')->sole();
});

it('shows the week as a grid of workers by days, with readable hours', function (): void {
    Livewire::test(RevisarHoras::class)
        ->assertOk()
        ->assertSee('Del 12/10 al 18/10/2026')
        ->assertSee('Ana Ruiz')
        ->assertSee('8 h')
        ->assertSee('Con avisos')
        ->call('semanaAnterior')
        ->assertSee('Del 05/10 al 11/10/2026')
        ->assertSee('No hay días por confirmar en esta semana');
});

it('confirms at once only the days without warnings', function (): void {
    expect($this->conAviso->hasAnomalies())->toBeTrue();

    Livewire::test(RevisarHoras::class)
        ->callAction('confirmarBuenos')
        ->assertNotified('Se confirmó 1 día');

    expect($this->limpio->refresh()->status)->toBe(AttendanceDayStatus::Confirmada)
        ->and($this->conAviso->refresh()->status)->toBe(AttendanceDayStatus::Propuesta);
});

it('opens a day on the side and confirms it from there', function (): void {
    Livewire::test(RevisarHoras::class)
        ->mountAction('dia', ['day' => $this->limpio->id])
        ->assertMountedActionModalSee('Marcas de la portería')
        ->assertMountedActionModalSee('06:00 am')
        ->callMountedAction()
        ->assertNotified('Día confirmado');

    expect($this->limpio->refresh()->status)->toBe(AttendanceDayStatus::Confirmada);
});

it('confirms the clean days from «Horas por confirmar» too', function (): void {
    Livewire::test(ListAttendanceDays::class)
        ->callAction(TestAction::make('confirmarBuenos')->table())
        ->assertNotified('Se confirmó 1 día');

    expect($this->limpio->refresh()->status)->toBe(AttendanceDayStatus::Confirmada)
        ->and($this->conAviso->refresh()->status)->toBe(AttendanceDayStatus::Propuesta);
});
