<?php

use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Models\MaintenanceBudget;
use App\Models\MaintenanceBudgetExpense;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\PlantMonthlyKpi;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrder;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\PermissionSeeder;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(ProvisionTenantBaseStructure::class)->handle($this->tenant);

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    (new DemoActivitySeeder)->run($this->tenant);

    $this->plant = $this->tenant->plants()->withoutGlobalScopes()->first();
    $this->closedMonth = now()->startOfMonth()->subMonthNoOverflow();
});

// ── El número que el ingeniero reconoce ──────────────────────────────────────

it('reproduces the plant reference figures in the closed month', function (): void {
    $kpis = app(PlantKpiService::class)->calculate(
        $this->plant,
        $this->closedMonth->copy()->startOfMonth(),
        $this->closedMonth->copy()->endOfMonth(),
    );

    expect($kpis['programmed_hours'])->toBe(452.0)   // HP
        ->and($kpis['cleaning_hours'])->toBe(8.0)     // HASEO
        ->and($kpis['effective_hours'])->toBe(420.0)  // HPREN
        ->and($kpis['processed_tons'])->toBe(6000.0)  // FP
        ->and($kpis['efficiency_percentage'])->toBe(94.59)
        ->and($kpis['productivity_tons_per_hour'])->toBe(13.51)
        ->and($kpis['availability_percentage'])->toBe(95.13);
});

it('splits the lost hours exactly as the plant sheet does', function (): void {
    $kpis = app(PlantKpiService::class)->calculate(
        $this->plant,
        $this->closedMonth->copy()->startOfMonth(),
        $this->closedMonth->copy()->endOfMonth(),
    );

    // HASEO 8 + HMTTO 14 + HOPER 10 = 32 h perdidas.
    expect($kpis['maintenance_lost_hours'] - $kpis['cleaning_hours'])->toBe(14.0)
        ->and($kpis['other_lost_hours'])->toBe(10.0)
        ->and($kpis['lost_hours'])->toBe(32.0);
});

it('freezes the closed month so the history chart has a bar', function (): void {
    $snapshot = PlantMonthlyKpi::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)
        ->where('year', (int) $this->closedMonth->year)
        ->where('month', (int) $this->closedMonth->month)
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->efficiency_percentage)->toBe(94.59)
        ->and($snapshot->productivity_tons_per_hour)->toBe(13.51);
});

// ── El trabajo: hecho y por hacer ────────────────────────────────────────────

it('leaves work orders both closed and open', function (): void {
    $byStatus = WorkOrder::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->get()
        ->groupBy(fn (WorkOrder $wo): string => $wo->status->value);

    expect($byStatus->get(WorkOrderStatus::Closed->value))->toHaveCount(22)
        ->and($byStatus->get(WorkOrderStatus::Draft->value))->toHaveCount(13);
});

it('gives the plant preventive plans to schedule', function (): void {
    expect(MaintenancePlan::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(15);
});

it('leaves requests waiting in every stage of the intake flow', function (): void {
    $statuses = MaintenanceRequest::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->pluck('status')
        ->map(fn ($status): string => $status->value)
        ->unique();

    expect($statuses)->toContain('submitted', 'under_review', 'approved', 'rejected');
});

// ── PPT: el presupuesto se llena solo ────────────────────────────────────────

it('records the budget expenses through closing work orders, not by hand', function (): void {
    // El seeder nunca escribe un gasto: los crea el listener al cerrar cada OT,
    // que es lo que ocurre en producción. Si esto sale en cero, el flujo real
    // está roto aunque el ejemplo se vea bien.
    $expenses = MaintenanceBudgetExpense::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->get();

    expect($expenses)->not->toBeEmpty()
        ->and($expenses->map(fn (MaintenanceBudgetExpense $e): string => $e->category->value)->unique()->values()->all())
        ->toEqualCanonicalizing(['mano_de_obra', 'repuestos', 'lubricantes']);
});

it('keeps the spending under the budget it was given', function (): void {
    $budget = MaintenanceBudget::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)
        ->where('year', (int) $this->closedMonth->year)
        ->where('month', (int) $this->closedMonth->month)
        ->first();

    $spent = MaintenanceBudgetExpense::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)
        ->whereYear('expense_date', $this->closedMonth->year)
        ->whereMonth('expense_date', $this->closedMonth->month)
        ->sum('amount');

    // Un ejemplo con el presupuesto reventado enseña una alarma, no el sistema.
    expect($budget)->not->toBeNull()
        ->and((float) $spent)->toBeGreaterThan(0)
        ->and((float) $spent)->toBeLessThan((float) $budget->amount);
});

// ── Inventario ───────────────────────────────────────────────────────────────

it('stocks the warehouse and leaves some parts below the minimum', function (): void {
    $stock = WarehouseSparePart::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->with('sparePart')
        ->get();

    $belowMinimum = $stock->filter(
        fn (WarehouseSparePart $row): bool => $row->current_stock < $row->sparePart->minimum_stock
    );

    expect($stock)->toHaveCount(12)
        // Un almacén donde nunca falta nada no enseña para qué sirve el mínimo.
        ->and($belowMinimum)->toHaveCount(3);
});

// ── Re-ejecutable ────────────────────────────────────────────────────────────

it('corrects instead of duplicating when it runs twice', function (): void {
    $before = [
        'ots' => WorkOrder::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count(),
        'planes' => MaintenancePlan::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count(),
        'solicitudes' => MaintenanceRequest::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count(),
        'stock' => WarehouseSparePart::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count(),
    ];

    (new DemoActivitySeeder)->run($this->tenant);

    expect(WorkOrder::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe($before['ots'])
        ->and(MaintenancePlan::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe($before['planes'])
        ->and(MaintenanceRequest::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe($before['solicitudes'])
        ->and(WarehouseSparePart::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe($before['stock']);
});

it('does not double the reference figures when it runs twice', function (): void {
    (new DemoActivitySeeder)->run($this->tenant);

    $kpis = app(PlantKpiService::class)->calculate(
        $this->plant,
        $this->closedMonth->copy()->startOfMonth(),
        $this->closedMonth->copy()->endOfMonth(),
    );

    expect($kpis['programmed_hours'])->toBe(452.0)
        ->and($kpis['processed_tons'])->toBe(6000.0)
        ->and($kpis['lost_hours'])->toBe(32.0);
});
