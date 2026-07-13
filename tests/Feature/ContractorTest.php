<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\DailyScheduleService;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Reports\Services\DailySchedulePdfService;
use App\Exceptions\BusinessRuleException;
use App\Models\Contractor;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTechnician;
use Illuminate\Support\Carbon;

/**
 * A1 — el costo real de mantenimiento incluye a quien no es empleado.
 *
 * Disam y Montajes Industriales HF ejecutan trabajo en El Pajuil hoy: están en la
 * hoja de vida y encabezan filas de la programación diaria. Si Fronda no puede
 * nombrarlos, el programa impreso sale incompleto y la OT que ejecutó un tercero
 * cuesta cero.
 */
beforeEach(function (): void {
    $this->service = app(WorkOrderService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
    $this->contractor = Contractor::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Montajes Industriales HF',
        'currency_code' => 'COP',
    ]);
    $this->today = Carbon::parse('2026-07-14 06:00:00');
});

/** @param  array<string, mixed>  $overrides */
function contractedWorkOrder(array $overrides = []): WorkOrder
{
    return WorkOrder::factory()->create([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'equipment_id' => test()->equipment->id,
        'status' => WorkOrderStatus::Planned,
        'planned_start_at' => test()->today,
        ...$overrides,
    ]);
}

// ── El costo (G3) ────────────────────────────────────────────────────────────

it('puts what the contractor charged into the work order external cost', function (): void {
    $workOrder = contractedWorkOrder();

    $this->service->assignContractor(
        $workOrder,
        $this->contractor,
        agreedCost: 4_500_000,
        scope: 'Montaje tubo madre de 8" del esterilizador',
    );

    expect($workOrder->refresh()->actual_cost_external)->toEqual('4500000.00')
        ->and($workOrder->actual_cost_total)->toEqual('4500000.00');
});

it('adds up two contractors on the same work order', function (): void {
    $other = Contractor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Disam']);
    $workOrder = contractedWorkOrder();

    $this->service->assignContractor($workOrder, $this->contractor, agreedCost: 3_000_000);
    $this->service->assignContractor($workOrder, $other, agreedCost: 1_000_000);

    expect($workOrder->refresh()->actual_cost_external)->toEqual('4000000.00');
});

it('freezes what was agreed even if the contractor rate changes later', function (): void {
    $workOrder = contractedWorkOrder();

    $this->service->assignContractor($workOrder, $this->contractor, agreedCost: 2_000_000);

    // Suben la tarifa en septiembre. Lo que costó el trabajo de junio no se mueve.
    $this->contractor->update(['hourly_rate' => 999_999]);

    expect($workOrder->refresh()->actual_cost_external)->toEqual('2000000.00');
});

it('does not wipe a manually typed external cost when no contractor was priced', function (): void {
    $workOrder = contractedWorkOrder();

    $this->service->updateCosts($workOrder, ['actual_cost_external' => 750_000]);
    // Se registra quién lo hizo, pero todavía no llegó la factura.
    $this->service->assignContractor($workOrder, $this->contractor);

    expect($workOrder->refresh()->actual_cost_external)->toEqual('750000.00');
});

it('takes the contractor cost off the work order when the assignment is removed', function (): void {
    $workOrder = contractedWorkOrder();

    $this->service->assignContractor($workOrder, $this->contractor, agreedCost: 2_000_000);
    $this->service->removeContractor($workOrder, $this->contractor);

    expect($workOrder->refresh()->actual_cost_total)->toBeNull();
});

it('does not assign the same contractor twice', function (): void {
    $workOrder = contractedWorkOrder();

    $this->service->assignContractor($workOrder, $this->contractor, agreedCost: 1_000_000);
    $this->service->assignContractor($workOrder, $this->contractor, agreedCost: 1_500_000);

    // Corrige lo pactado, no duplica la línea (ni el costo).
    expect($workOrder->contractors()->count())->toBe(1)
        ->and($workOrder->refresh()->actual_cost_external)->toEqual('1500000.00');
});

// ── Multi-tenant ─────────────────────────────────────────────────────────────

it('refuses a contractor from another company', function (): void {
    $other = Tenant::factory()->create();
    $stranger = Contractor::factory()->create(['tenant_id' => $other->id]);

    $this->service->assignContractor(contractedWorkOrder(), $stranger, agreedCost: 100);
})->throws(BusinessRuleException::class, 'no existe en esta organización');

// ── El papel (desbloquea F3) ─────────────────────────────────────────────────

it('gives the contractor his own sheet in the printed programme', function (): void {
    $workOrder = contractedWorkOrder(['title' => 'Montaje sistema de alimentación de vapor']);
    $this->service->assignContractor($workOrder, $this->contractor, agreedCost: 4_500_000);

    $groups = app(DailyScheduleService::class)->forDay($this->tenant->id, $this->today)['groups'];

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['technician'])->toBe('Montajes Industriales HF')
        ->and($groups[0]['is_contractor'])->toBeTrue();
});

it('does not call a work order unassigned when a contractor owns it', function (): void {
    $this->service->assignContractor(contractedWorkOrder(), $this->contractor);

    // Antes de A1 esta OT figuraba «sin técnico» y el planificador la buscaba en vano.
    expect(app(DailyScheduleService::class)->forDay($this->tenant->id, $this->today)['unassigned_count'])
        ->toBe(0);
});

it('keeps employees and contractors on separate sheets of the same programme', function (): void {
    $technician = User::factory()->create(['name' => 'Andrey Moreno']);

    $ownWork = contractedWorkOrder();
    WorkOrderTechnician::create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $ownWork->id,
        'user_id' => $technician->id,
        'role' => 'technician',
    ]);

    $this->service->assignContractor(contractedWorkOrder(), $this->contractor);

    $groups = app(DailyScheduleService::class)->forDay($this->tenant->id, $this->today)['groups'];

    expect($groups)->toHaveCount(2)
        ->and(collect($groups)->firstWhere('technician', 'Andrey Moreno')['is_contractor'])->toBeFalse()
        ->and(collect($groups)->firstWhere('technician', 'Montajes Industriales HF')['is_contractor'])->toBeTrue();
});

it('prints a programme whose responsible is a contractor', function (): void {
    $this->service->assignContractor(contractedWorkOrder(), $this->contractor, agreedCost: 4_500_000);

    expect(app(DailySchedulePdfService::class)->generate($this->tenant->id, $this->today))
        ->toStartWith('%PDF');
});
