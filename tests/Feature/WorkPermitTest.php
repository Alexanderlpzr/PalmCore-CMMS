<?php

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Enums\WorkPermitStatus;
use App\Domain\Maintenance\Enums\WorkPermitType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Maintenance\Services\WorkPermitService;
use App\Exceptions\BusinessRuleException;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkPermit;

/**
 * A2 / G4 — nadie entra a un espacio confinado sin permiso.
 *
 * El permiso no es un adjunto ni una casilla: si no bloquea la ejecución, es papel.
 * Estos tests existen para que ese bloqueo no se pueda desactivar sin romperlos.
 */
beforeEach(function (): void {
    $this->permits = app(WorkPermitService::class);
    $this->workOrders = app(WorkOrderService::class);
    $this->tenant = Tenant::factory()->create();
    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hse = User::factory()->create(['name' => 'Jefe HSE']);
    $this->technician = User::factory()->create(['name' => 'Técnico']);
});

/** Una OT que declara necesitar los permisos indicados. */
function workOrderRequiring(array $types, WorkOrderType $type = WorkOrderType::Corrective): WorkOrder
{
    $workOrder = test()->workOrders->create([
        'tenant_id' => test()->tenant->id,
        'equipment_id' => test()->equipment->id,
        'work_order_type' => $type->value,
        'priority' => 'p3_medium',
        'title' => 'Soldadura en tubería de vapor',
        'description' => 'desc',
        'required_permit_types' => array_map(fn (WorkPermitType $t): string => $t->value, $types),
    ], test()->hse);

    test()->workOrders->assignTechnician($workOrder, test()->technician, TechnicianRole::Technician);

    return $workOrder->refresh();
}

/** @param  array<string, mixed>  $overrides */
function issuePermit(WorkOrder $workOrder, WorkPermitType $type, array $overrides = []): WorkPermit
{
    return test()->permits->issue($workOrder, $type, [
        'hazards' => 'Chispa sobre material combustible.',
        'controls' => 'Vigía de fuego y extintor en sitio.',
        'valid_from' => now()->subMinutes(10),
        'valid_until' => now()->addHours(8),
        'isolation_points' => $type->requiresIsolation() ? ['Breaker CCM-04 con candado'] : null,
        ...$overrides,
    ], test()->hse);
}

function start(WorkOrder $workOrder): WorkOrder
{
    test()->workOrders->transition($workOrder, WorkOrderStatus::Planned, test()->hse);

    return test()->workOrders->transition($workOrder->refresh(), WorkOrderStatus::InProgress, test()->technician);
}

// ── La puerta (G4) ───────────────────────────────────────────────────────────

it('refuses to start hot work without a permit', function (): void {
    start(workOrderRequiring([WorkPermitType::HotWork]));
})->throws(BusinessRuleException::class, 'Trabajo en caliente');

it('refuses to start on a permit that was issued but nobody signed', function (): void {
    $workOrder = workOrderRequiring([WorkPermitType::HotWork]);

    // Emitido por HSE, pero al técnico nadie le explicó los riesgos.
    issuePermit($workOrder, WorkPermitType::HotWork);

    start($workOrder);
})->throws(BusinessRuleException::class, 'firmado y vigente');

it('refuses to start on an expired permit', function (): void {
    $workOrder = workOrderRequiring([WorkPermitType::HotWork]);

    $permit = issuePermit($workOrder, WorkPermitType::HotWork, [
        'valid_from' => now()->subHours(10),
        'valid_until' => now()->addMinutes(5),
    ]);

    $this->permits->accept($permit, $this->technician);

    // El permiso de la mañana no cubre la chispa de la tarde.
    $this->travel(10)->minutes();

    start($workOrder);
})->throws(BusinessRuleException::class, 'firmado y vigente');

it('lets the work start once the permit is signed and valid', function (): void {
    $workOrder = workOrderRequiring([WorkPermitType::HotWork]);

    $permit = issuePermit($workOrder, WorkPermitType::HotWork);
    $this->permits->accept($permit, $this->technician);

    expect(start($workOrder)->status)->toBe(WorkOrderStatus::InProgress);
});

it('demands every declared permit, not just one of them', function (): void {
    $workOrder = workOrderRequiring([WorkPermitType::HotWork, WorkPermitType::ConfinedSpace]);

    $hotWork = issuePermit($workOrder, WorkPermitType::HotWork);
    $this->permits->accept($hotWork, $this->technician);

    // Falta el de espacio confinado: entrar al digestor sigue prohibido.
    start($workOrder);
})->throws(BusinessRuleException::class, 'Espacio confinado');

it('does not block a work order that needs no permit', function (): void {
    expect(start(workOrderRequiring([]))->status)->toBe(WorkOrderStatus::InProgress);
});

it('does not let an emergency start itself when it needs a permit', function (): void {
    // La urgencia no autoriza a nadie a entrar a un digestor sin permiso.
    $emergency = workOrderRequiring([WorkPermitType::ConfinedSpace], WorkOrderType::Emergency);

    expect($emergency->status)->toBe(WorkOrderStatus::Draft)
        ->and($emergency->actual_start_at)->toBeNull();
});

it('still lets an emergency with no permits start immediately', function (): void {
    expect(workOrderRequiring([], WorkOrderType::Emergency)->status)->toBe(WorkOrderStatus::InProgress);
});

// ── Las dos firmas ───────────────────────────────────────────────────────────

it('does not let the issuer sign his own permit as the executor', function (): void {
    $permit = issuePermit(workOrderRequiring([WorkPermitType::HotWork]), WorkPermitType::HotWork);

    // Un permiso auto-emitido y auto-firmado es un trámite consigo mismo.
    $this->permits->accept($permit, $this->hse);
})->throws(BusinessRuleException::class, 'no puede firmarlo como ejecutante');

it('refuses to sign a permit that already expired', function (): void {
    $permit = issuePermit(workOrderRequiring([WorkPermitType::HotWork]), WorkPermitType::HotWork, [
        'valid_from' => now()->subDays(2),
        'valid_until' => now()->subDay(),
    ]);

    $this->permits->accept($permit, $this->technician);
})->throws(BusinessRuleException::class, 'ya venció');

// ── LOTO ─────────────────────────────────────────────────────────────────────

it('refuses a confined space permit with no isolation points', function (): void {
    // Sin bloqueo, el equipo sigue energizado con alguien adentro.
    $this->permits->issue(workOrderRequiring([WorkPermitType::ConfinedSpace]), WorkPermitType::ConfinedSpace, [
        'hazards' => 'Atmósfera deficiente de oxígeno.',
        'controls' => 'Vigía externo.',
        'valid_from' => now(),
        'valid_until' => now()->addHours(4),
        'isolation_points' => [],
    ], $this->hse);
})->throws(BusinessRuleException::class, 'puntos de aislamiento');

it('keeps the isolation points on the permit', function (): void {
    $workOrder = workOrderRequiring([WorkPermitType::LockoutTagout]);

    $permit = issuePermit($workOrder, WorkPermitType::LockoutTagout, [
        'isolation_points' => ['Breaker CCM-04 con candado rojo', 'Válvula V-12 cerrada y etiquetada'],
    ]);

    expect($permit->isolation_points)->toHaveCount(2);
});

// ── El candado se retira ─────────────────────────────────────────────────────

it('closes the permit when the work is finished', function (): void {
    $workOrder = workOrderRequiring([WorkPermitType::HotWork]);

    $permit = issuePermit($workOrder, WorkPermitType::HotWork);
    $this->permits->accept($permit, $this->technician);

    $this->workOrders->transition(start($workOrder), WorkOrderStatus::Completed, $this->technician);

    // Un permiso abierto es un equipo que sigue bloqueado y nadie sabe por qué.
    expect($permit->refresh()->status)->toBe(WorkPermitStatus::Closed)
        ->and($permit->closed_at)->not->toBeNull();
});

it('does not issue two live permits of the same type for the same work', function (): void {
    $workOrder = workOrderRequiring([WorkPermitType::HotWork]);

    issuePermit($workOrder, WorkPermitType::HotWork);
    issuePermit($workOrder, WorkPermitType::HotWork);
})->throws(BusinessRuleException::class, 'ya tiene un permiso');

// ── La firma en campo (PWA) ──────────────────────────────────────────────────

it('lets the technician sign the permit from the field', function (): void {
    $workOrder = workOrderRequiring([WorkPermitType::ConfinedSpace]);
    $permit = issuePermit($workOrder, WorkPermitType::ConfinedSpace);

    $this->technician->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $token = $this->technician->createToken('pwa', ['permits.read', 'permits.write']);
    $token->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();

    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    // Lo primero que lee el técnico: ¿este papel me autoriza ahora?
    $this->getJson("/api/v1/work-orders/{$workOrder->id}/permits", $headers)
        ->assertOk()
        ->assertJsonPath('data.0.authorizes_work_now', false)
        ->assertJsonPath('data.0.isolation_points.0', 'Breaker CCM-04 con candado');

    $this->patchJson("/api/v1/work-permits/{$permit->id}/accept", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.authorizes_work_now', true);

    // Y ahora sí arranca.
    expect(start($workOrder->refresh())->status)->toBe(WorkOrderStatus::InProgress);
});

it('does not let a token without the permits ability sign anything', function (): void {
    $permit = issuePermit(workOrderRequiring([WorkPermitType::HotWork]), WorkPermitType::HotWork);

    $this->technician->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $token = $this->technician->createToken('pwa', ['work-orders.read']);
    $token->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();

    $this->patchJson(
        "/api/v1/work-permits/{$permit->id}/accept",
        [],
        ['Authorization' => 'Bearer '.$token->plainTextToken],
    )->assertForbidden();
});

it('numbers permits sequentially within the tenant', function (): void {
    $first = issuePermit(workOrderRequiring([WorkPermitType::HotWork]), WorkPermitType::HotWork);
    $second = issuePermit(workOrderRequiring([WorkPermitType::HotWork]), WorkPermitType::HotWork);

    expect($first->permit_number)->toBe('PT-'.now()->year.'-00001')
        ->and($second->permit_number)->toBe('PT-'.now()->year.'-00002');
});
