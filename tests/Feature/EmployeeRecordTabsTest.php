<?php

use App\Domain\HumanResources\Enums\BonusType;
use App\Domain\HumanResources\Enums\NoveltyType;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\BonusesRelationManager;
use App\Filament\Resources\Employees\RelationManagers\DeductionsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\NoveltiesRelationManager;
use App\Http\Middleware\SyncSpatieTeamId;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeNovelty;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/*
 * Las pestañas del trabajador que crean registros: novedades, bonificaciones y
 * descuentos. En cada clic del panel, Livewire corre los middleware persistentes con una
 * petición falsa y sigue con la acción fuera de ellos; SyncSpatieTeamId limpiaba la
 * empresa en ese momento, y lo creado nacía sin `tenant_id`. En producción, talento
 * humano no pudo agregar un bono el 2026-10-06.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $rrhh = User::factory()->create(['is_active' => true]);
    $rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $rrhh->assignRole('talento-humano');
    $this->actingAs($rrhh);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
});

/** Lo que hace Livewire en cada clic: corre el middleware y sigue fuera de él. */
function clicDelPanel(): void
{
    (new SyncSpatieTeamId)->handle(Request::create('/'), fn (): Response => new Response);
}

it('keeps the company for the whole click, and lets it go when the response is out', function (): void {
    clicDelPanel();

    expect(CurrentTenant::id())->toBe($this->tenant->id);

    (new SyncSpatieTeamId)->terminate(Request::create('/'), new Response);

    expect(CurrentTenant::isSet())->toBeFalse();
});

it('adds a bonus from the worker record', function (): void {
    clicDelPanel();

    Livewire::test(BonusesRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('create')->table(), [
            'type' => BonusType::NoConstitutiva->value,
            'concept' => 'Bono supernumerario',
            'amount' => 367850,
            'prorate_by_worked_days' => true,
            'effective_from' => '2026-10-01',
            'effective_to' => null,
        ])
        ->assertHasNoFormErrors();

    $bonus = EmployeeBonus::query()->forTenant($this->tenant->id)->sole();

    expect($bonus->tenant_id)->toBe($this->tenant->id)
        ->and($bonus->employee_id)->toBe($this->employee->id)
        ->and($bonus->prorate_by_worked_days)->toBeTrue();
});

it('adds a novelty from the worker record', function (): void {
    clicDelPanel();

    Livewire::test(NoveltiesRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('create')->table(), [
            'type' => NoveltyType::PermisoAutorizado->value,
            'starts_on' => '2026-10-05',
            'ends_on' => '2026-10-05',
        ])
        ->assertHasNoFormErrors();

    expect(EmployeeNovelty::query()->forTenant($this->tenant->id)->sole()->tenant_id)->toBe($this->tenant->id);
});

it('adds a deduction from the worker record', function (): void {
    clicDelPanel();

    Livewire::test(DeductionsRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('create')->table(), [
            'concept' => 'Seguro funerario',
            'amount' => 15000,
            'effective_from' => '2026-10-01',
        ])
        ->assertHasNoFormErrors();

    expect(EmployeeDeduction::query()->forTenant($this->tenant->id)->sole()->tenant_id)->toBe($this->tenant->id);
});
