<?php

use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * El número de OT es un secuencial simple por organización: OT-0001, OT-0002…
 *
 * Antes traía año y código de equipo (OT-2026-A02STR.02.01-000006). La cuenta
 * es global dentro del tenant, no por equipo, y el orden se calcula sobre el
 * entero para que «OT-10000» no quede por debajo de «OT-9999».
 */
function createWorkOrderFor(Tenant $tenant, Equipment $equipment, User $user): WorkOrder
{
    return app(WorkOrderService::class)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'OT de prueba',
        'description' => 'desc',
    ], $user);
}

it('numera las OT como OT-0001, OT-0002, OT-0003 sin importar el equipo', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $equipZZZ = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'ZZZ-EQ']);
    $equipAAA = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'AAA-EQ']);

    expect(createWorkOrderFor($tenant, $equipZZZ, $user)->work_order_number)->toBe('OT-0001')
        ->and(createWorkOrderFor($tenant, $equipAAA, $user)->work_order_number)->toBe('OT-0002')
        ->and(createWorkOrderFor($tenant, $equipZZZ, $user)->work_order_number)->toBe('OT-0003');
});

it('la cuenta es independiente en cada organización', function () {
    $user = User::factory()->create();

    $tenantA = Tenant::factory()->create();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $tenantB = Tenant::factory()->create();
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    createWorkOrderFor($tenantA, $equipA, $user);

    expect(createWorkOrderFor($tenantA, $equipA, $user)->work_order_number)->toBe('OT-0002')
        ->and(createWorkOrderFor($tenantB, $equipB, $user)->work_order_number)->toBe('OT-0001');
});

it('ignora los números heredados con año y código de equipo', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'A02STR.02.01']);

    // Una OT vieja: no debe entrar en la cuenta ni desordenarla.
    WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_number' => 'OT-2026-A02STR.02.01-000006',
    ]);

    expect(createWorkOrderFor($tenant, $equipment, $user)->work_order_number)->toBe('OT-0001');
});

it('sigue la cuenta desde el mayor, no desde el conteo de filas', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_number' => 'OT-0042',
    ]);

    expect(createWorkOrderFor($tenant, $equipment, $user)->work_order_number)->toBe('OT-0043');
});

it('no repite números al crear varias OT seguidas', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $numbers = [];

    foreach (['TES-00', 'EQ-SLB', 'E2E-PRE-001', 'AAA-EQ', 'ZZZ-EQ'] as $code) {
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => $code]);
        $numbers[] = createWorkOrderFor($tenant, $equipment, $user)->work_order_number;
    }

    expect($numbers)->toBe(['OT-0001', 'OT-0002', 'OT-0003', 'OT-0004', 'OT-0005']);
});
