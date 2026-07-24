<?php

use App\Domain\Analytics\Services\BudgetTrackingService;
use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Filament\Pages\Presupuesto;
use App\Models\MaintenanceBudget;
use App\Models\MaintenanceBudgetExpense;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('el reporte mensual suma los gastos, calcula el restante y el desglose por concepto', function (): void {
    MaintenanceBudget::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 7, 'amount' => 1_500_000,
    ]);
    MaintenanceBudgetExpense::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'expense_date' => '2026-07-03', 'amount' => 500_000, 'category' => ExpenseCategory::Repuestos->value,
    ]);
    MaintenanceBudgetExpense::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'expense_date' => '2026-07-10', 'amount' => 300_000, 'category' => ExpenseCategory::ManoDeObra->value,
    ]);
    // Un gasto de otro mes que no debe contar.
    MaintenanceBudgetExpense::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'expense_date' => '2026-06-20', 'amount' => 999_999, 'category' => ExpenseCategory::Otros->value,
    ]);

    $report = app(BudgetTrackingService::class)->monthlyReport($this->plant, 2026, 7);

    expect($report['budget'])->toBe(1_500_000.0)
        ->and($report['total'])->toBe(800_000.0)
        ->and($report['remaining'])->toBe(700_000.0)
        ->and($report['percent_used'])->toBe(53.3)
        ->and($report['is_over_budget'])->toBeFalse()
        ->and($report['by_category']['repuestos'])->toBe(500_000.0)
        // Acumulado: semana 1 (día 3) 500k, semana 2 (día 10) 800k.
        ->and($report['weekly']['accumulated'][0])->toBe(500_000.0)
        ->and($report['weekly']['accumulated'][1])->toBe(800_000.0);
});

it('agregar gasto crea un registro para la planta y el mes del filtro', function (): void {
    Livewire::test(Presupuesto::class)
        ->set('filters.plant_id', $this->plant->id)
        ->set('filters.year', 2026)
        ->set('filters.month', 7)
        ->callAction('addExpense', data: [
            'expense_date' => '2026-07-05',
            'category' => ExpenseCategory::Repuestos->value,
            'amount' => 250_000,
        ])
        ->assertHasNoActionErrors();

    $expense = MaintenanceBudgetExpense::where('plant_id', $this->plant->id)->sole();

    expect((float) $expense->amount)->toBe(250_000.0)
        ->and($expense->category)->toBe(ExpenseCategory::Repuestos)
        ->and($expense->created_by)->toBe($this->user->id);
});

it('asignar presupuesto crea o actualiza el techo del mes', function (): void {
    Livewire::test(Presupuesto::class)
        ->set('filters.plant_id', $this->plant->id)
        ->set('filters.year', 2026)
        ->set('filters.month', 7)
        ->callAction('assignBudget', data: ['amount' => 2_000_000])
        ->assertHasNoActionErrors();

    expect((float) MaintenanceBudget::where('plant_id', $this->plant->id)
        ->where('year', 2026)->where('month', 7)->value('amount'))->toBe(2_000_000.0);
});
