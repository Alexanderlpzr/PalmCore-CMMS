<?php

use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Models\Equipment;
use App\Models\MaintenanceBudgetExpense;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
});

function costEditUser(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user;
}

it('lets an administrator manually override the OT cost broken down by rubro', function () {
    $admin = costEditUser($this->tenant, 'administrador-general');
    $wo = app(WorkOrderService::class)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test',
        'description' => 'desc',
    ], $admin);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->callAction(TestAction::make('edit_costs'), data: [
            'actual_cost_labor' => 40000,
            'actual_cost_parts' => 25000,
            'actual_cost_consumables' => 10000,
        ])
        ->assertHasNoActionErrors();

    expect($wo->fresh()->actual_cost_labor)->toBe(40000.0)
        ->and($wo->fresh()->actual_cost_parts)->toBe(25000.0)
        ->and($wo->fresh()->actual_cost_consumables)->toBe(10000.0)
        ->and($wo->fresh()->actual_cost_total)->toBe(75000.0);
});

it('closing via the real close action with los tres rubros creates the three budget expenses', function () {
    // Regresión: reproduce el reporte del cliente ("cerré con 3 costos distintos y
    // en presupuesto solo aparecieron 2") pasando por la acción real de Filament
    // (no por el servicio directo), tal como lo hace un cierre desde el navegador.
    $admin = costEditUser($this->tenant, 'administrador-general');
    $wo = app(WorkOrderService::class)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Mantenimiento — Unitropico',
        'description' => 'desc',
    ], $admin);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->callAction(TestAction::make('close'), data: [
            'work_performed' => 'Cambio de rodamiento',
            'actual_cost_labor' => 150000,
            'actual_cost_parts' => 90000,
            'actual_cost_consumables' => 80000,
        ])
        ->assertHasNoActionErrors();

    expect($wo->fresh()->actual_cost_total)->toBe(320000.0);

    $expenses = MaintenanceBudgetExpense::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->get();

    expect($expenses)->toHaveCount(3)
        ->and($expenses->firstWhere('category', ExpenseCategory::ManoDeObra)?->amount)->toBe(150000.0)
        ->and($expenses->firstWhere('category', ExpenseCategory::Repuestos)?->amount)->toBe(90000.0)
        ->and($expenses->firstWhere('category', ExpenseCategory::Lubricantes)?->amount)->toBe(80000.0);
});

it('hides the cost edit action from a técnico', function () {
    $tech = costEditUser($this->tenant, 'tecnico');
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $this->actingAs($tech);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->assertActionHidden('edit_costs');
});
