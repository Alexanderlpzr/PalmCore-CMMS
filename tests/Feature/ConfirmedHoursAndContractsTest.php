<?php

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Pages\HorasConfirmadas;
use App\Filament\Pages\HorasExtras;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\RelationManagers\OvertimeRelationManager;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Las horas confirmadas se corrigen cuando llega una novedad tarde —desde «Horas
 * confirmadas», desde «Horas extras» y desde la ficha— sin perder la firma. Y el contrato
 * a término fijo lleva su fecha de terminación, propuesta en tramos de 3 meses, con aviso a
 * 30 días.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);
    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Diego', 'last_name' => 'Medina']);
    $carne = EmployeeQrCode::factory()->forEmployee($this->employee)->create();

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');

    app(AttendanceService::class)->record($carne, at: Carbon::parse('2026-10-05 06:00', 'America/Bogota')->utc());
    app(AttendanceService::class)->record($carne, at: Carbon::parse('2026-10-05 16:00', 'America/Bogota')->utc());
    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-10-05'), Carbon::parse('2026-10-05'));
    $this->day = AttendanceDay::query()->where('employee_id', $this->employee->id)->sole();
    app(AttendanceDayConfirmer::class)->confirm($this->day, $this->rrhh);

    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
    $this->travelTo(Carbon::parse('2026-10-08 10:00', 'America/Bogota'));
});

it('lists the confirmed days and adjusts one without losing the signature', function (): void {
    Livewire::test(HorasConfirmadas::class)
        ->assertCanSeeTableRecords([$this->day])
        ->callAction(TestAction::make('ajustarHoras')->table($this->day), [
            'ordinary_hours' => 8, 'night_surcharge_hours' => 0, 'sunday_surcharge_hours' => 0, 'night_sunday_surcharge_hours' => 0,
            'overtime_day_hours' => 0, 'overtime_night_hours' => 0, 'overtime_sunday_day_hours' => 0, 'overtime_sunday_night_hours' => 0,
            'reason' => 'Las dos extras no se autorizaron',
        ])
        ->assertHasNoFormErrors();

    expect($this->day->refresh()->status)->toBe(AttendanceDayStatus::Confirmada)
        ->and((float) $this->day->overtime_day_hours)->toBe(0.0)
        ->and($this->day->adjustment_reason)->toBe('Las dos extras no se autorizaron');
});

it('voids a confirmed day from «Horas confirmadas»', function (): void {
    Livewire::test(HorasConfirmadas::class)
        ->callAction(TestAction::make('anularDia')->table($this->day), ['reason' => 'Era incapacidad, no trabajó'])
        ->assertHasNoFormErrors();

    expect(AttendanceDay::query()->whereKey($this->day->id)->exists())->toBeFalse();
});

it('opens the days of a worker from «Horas extras» and edits them from the record tab', function (): void {
    Livewire::test(HorasExtras::class)
        ->mountAction(TestAction::make('dias')->table($this->employee))
        ->assertMountedActionModalSee('Días de Diego Medina');

    Livewire::test(OvertimeRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])
        ->assertActionVisible(TestAction::make('ajustarHoras')->table($this->day))
        ->callAction(TestAction::make('anularDia')->table($this->day), ['reason' => 'Permiso, no trabajó'])
        ->assertHasNoFormErrors();

    expect(AttendanceDay::query()->whereKey($this->day->id)->exists())->toBeFalse();
});

it('proposes the end of a fixed-term contract from the hiring date and the months', function (): void {
    Livewire::test(CreateEmployee::class)
        ->fillForm(['contract_type' => 'fijo', 'hire_date' => '2026-10-01'])
        ->fillForm(['contract_months' => 6])
        ->assertSchemaStateSet(['contract_end_date' => '2027-03-31'])
        ->fillForm(['contract_months' => 3])
        ->assertSchemaStateSet(['contract_end_date' => '2026-12-31']);

    expect(Employee::fixedTermEnd(CarbonImmutable::parse('2026-11-30'), 3)->toDateString())->toBe('2027-02-28')
        ->and(Employee::fixedTermEnd(CarbonImmutable::parse('2026-10-15'), 12)->toDateString())->toBe('2027-10-14');
});

it('warns about fixed-term contracts that end within 30 days', function (): void {
    $vence = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'contract_type' => 'fijo', 'contract_end_date' => '2026-10-31']);
    $lejos = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'contract_type' => 'fijo', 'contract_end_date' => '2027-03-31']);

    expect($vence->contractEndsWithin(30))->toBeTrue()
        ->and($lejos->contractEndsWithin(30))->toBeFalse();

    Livewire::test(ListEmployees::class)
        ->assertSee('1 contrato a término fijo vence en los próximos 30 días')
        ->filterTable('contrato_por_vencer', true)
        ->assertCanSeeTableRecords([$vence])
        ->assertCanNotSeeTableRecords([$lejos, $this->employee]);
});
