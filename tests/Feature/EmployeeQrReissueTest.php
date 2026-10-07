<?php

use App\Domain\HumanResources\Enums\QrRevocationReason;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\RelationManagers\QrCodesRelationManager;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Reemitir un carné deja sin servicio el que el trabajador tiene en el bolsillo. Antes
 * estaba en la lista de Personal, junto a «Descargar QR», del mismo color, y bastaban dos
 * clics. Ahora vive en la ficha, pide motivo y cédula, y el viejo guarda quién y por qué.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');
    $this->actingAs($this->rrhh);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'document_number' => '1.116.615.860']);
    $this->card = EmployeeQrCode::factory()->forEmployee($this->employee)->create();
});

function pestanaDelCarne(Employee $employee)
{
    return Livewire::test(QrCodesRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => EditEmployee::class]);
}

it('no longer offers reissuing from the staff list, next to «Descargar QR»', function (): void {
    Livewire::test(ListEmployees::class)
        ->assertActionExists(TestAction::make('descargarQr')->table($this->employee))
        ->assertActionDoesNotExist(TestAction::make('reemitirCarne')->table($this->employee));
});

it('shows the badge tab to human resources but not to the gate', function (): void {
    expect(QrCodesRelationManager::canViewForRecord($this->employee, EditEmployee::class))->toBeTrue();

    $vigilante = User::factory()->create(['is_active' => true]);
    $vigilante->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $vigilante->assignRole('porteria');
    $this->actingAs($vigilante);

    expect(QrCodesRelationManager::canViewForRecord($this->employee, EditEmployee::class))->toBeFalse();
});

it('does not reissue with an ID number that is not the worker\'s', function (): void {
    pestanaDelCarne($this->employee)
        ->callAction(TestAction::make('reemitirCarne')->table(), [
            'reason' => QrRevocationReason::Perdido->value,
            'document_confirmation' => '1116615861',
        ])
        ->assertHasFormErrors(['document_confirmation']);

    expect($this->card->refresh()->is_active)->toBeTrue();
});

it('does not reissue without a reason', function (): void {
    pestanaDelCarne($this->employee)
        ->callAction(TestAction::make('reemitirCarne')->table(), [
            'reason' => null,
            'document_confirmation' => '1116615860',
        ])
        ->assertHasFormErrors(['reason']);

    expect($this->card->refresh()->is_active)->toBeTrue();
});

it('asks for the detail when the reason is «Otro»', function (): void {
    pestanaDelCarne($this->employee)
        ->callAction(TestAction::make('reemitirCarne')->table(), [
            'reason' => QrRevocationReason::Otro->value,
            'detail' => '',
            'document_confirmation' => '1116615860',
        ])
        ->assertHasFormErrors(['detail']);

    expect($this->card->refresh()->is_active)->toBeTrue();
});

it('reissues with the reason, keeps who and why on the old badge, and the gate rejects it', function (): void {
    $tokenViejo = $this->card->qr_token;

    pestanaDelCarne($this->employee)
        ->callAction(TestAction::make('reemitirCarne')->table(), [
            'reason' => QrRevocationReason::Perdido->value,
            'detail' => 'Lo perdió en el patio de fruta',
            // Con o sin puntos, la cédula es la misma.
            'document_confirmation' => '1116615860',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Carné reemitido');

    $viejo = EmployeeQrCode::withTrashed()->find($this->card->id);
    $nuevo = $this->employee->fresh()->qrCode;

    expect($viejo->trashed())->toBeTrue()
        ->and($viejo->is_active)->toBeFalse()
        ->and($viejo->revoked_by)->toBe($this->rrhh->id)
        ->and($viejo->revocation_reason)->toBe(QrRevocationReason::Perdido)
        ->and($viejo->revocation_detail)->toBe('Lo perdió en el patio de fruta')
        ->and($nuevo)->not->toBeNull()
        ->and($nuevo->qr_token)->not->toBe($tokenViejo);

    expect(fn () => app(AttendanceService::class)->resolveToken($tokenViejo, $this->tenant->id))
        ->toThrow(AttendanceException::class);

    // El historial muestra los dos: el vigente y el anulado.
    pestanaDelCarne($this->employee->fresh())
        ->assertCanSeeTableRecords([$nuevo, $viejo])
        ->assertSee('Vigente')
        ->assertSee('Anulado')
        ->assertSee('Perdido');
});

it('shows the dates in plant time, not in UTC', function (): void {
    $this->card->update(['generated_at' => Carbon::parse('2026-09-01 15:00:00', 'UTC')]);

    // 8:30 p. m. del 6 de octubre en la planta; en UTC ya es 7.
    $this->travelTo(Carbon::parse('2026-10-07 01:30:00', 'UTC'));

    pestanaDelCarne($this->employee)
        ->callAction(TestAction::make('reemitirCarne')->table(), [
            'reason' => QrRevocationReason::Danado->value,
            'document_confirmation' => '1116615860',
        ])
        ->assertHasNoFormErrors();

    // El nuevo se emitió y el viejo se anuló el 6, no el 7.
    pestanaDelCarne($this->employee->fresh())
        ->assertSee('01/09/2026')
        ->assertSee('06/10/2026')
        ->assertDontSee('07/10/2026');
});

it('issues a badge to a worker who has none', function (): void {
    // Sin el observador, que le encola el carné al crearlo.
    $sinCarne = Employee::withoutEvents(fn (): Employee => Employee::factory()->create(['tenant_id' => $this->tenant->id]));

    pestanaDelCarne($sinCarne)
        ->assertActionHidden(TestAction::make('reemitirCarne')->table())
        ->callAction(TestAction::make('emitirCarne')->table())
        ->assertNotified('Carné emitido')
        // Sin recargar: ya no ofrece emitir otro, y sí descargarlo o reemitirlo.
        ->assertActionHidden(TestAction::make('emitirCarne')->table())
        ->assertActionVisible(TestAction::make('descargarQr')->table())
        ->assertActionVisible(TestAction::make('reemitirCarne')->table());

    expect($sinCarne->fresh()->qrCode)->not->toBeNull();
});
