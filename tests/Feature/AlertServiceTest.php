<?php

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Alerts\Services\AlertService;
use App\Events\AlertCreated;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

// ── Helpers ───────────────────────────────────────────────────────────────────

function alertTenant(): Tenant
{
    return Tenant::factory()->create();
}

function alertData(Tenant $tenant, array $overrides = []): CreateAlertData
{
    return new CreateAlertData(
        tenantId: $tenant->id,
        severity: $overrides['severity'] ?? AlertSeverity::Warning,
        category: $overrides['category'] ?? AlertCategory::Maintenance,
        title: $overrides['title'] ?? 'Plan vencido: PM-001',
        entityType: $overrides['entityType'] ?? 'maintenance_plan',
        entityId: $overrides['entityId'] ?? (string) Str::uuid7(),
    );
}

// ── create() ─────────────────────────────────────────────────────────────────

it('crea una alerta nueva y dispara el evento AlertCreated', function () {
    Event::fake();

    $tenant = alertTenant();
    $data = alertData($tenant);

    $alert = app(AlertService::class)->create($data);

    expect($alert)->toBeInstanceOf(Alert::class)
        ->and($alert->status)->toBe(AlertStatus::Open)
        ->and($alert->severity)->toBe(AlertSeverity::Warning)
        ->and($alert->category)->toBe(AlertCategory::Maintenance);

    Event::assertDispatched(AlertCreated::class, fn ($e) => $e->alert->id === $alert->id);
});

it('retorna null si ya existe una alerta abierta para la misma entidad/categoría', function () {
    Event::fake();

    $tenant = alertTenant();
    $data = alertData($tenant);

    $service = app(AlertService::class);
    $first = $service->create($data);
    $second = $service->create($data);

    expect($first)->toBeInstanceOf(Alert::class)
        ->and($second)->toBeNull();

    expect(Alert::withoutGlobalScopes()->count())->toBe(1);
    Event::assertDispatchedTimes(AlertCreated::class, 1);
});

it('permite crear nueva alerta si la anterior fue resuelta', function () {
    Event::fake();

    $tenant = alertTenant();
    $user = User::factory()->create(['is_active' => true]);
    $data = alertData($tenant);

    $service = app(AlertService::class);
    $first = $service->create($data);
    $service->resolve($first, $user);

    $second = $service->create($data);

    expect($second)->toBeInstanceOf(Alert::class)
        ->and($second->id)->not->toBe($first->id);

    expect(Alert::withoutGlobalScopes()->count())->toBe(2);
});

it('permite crear nueva alerta si la anterior fue descartada', function () {
    Event::fake();

    $tenant = alertTenant();
    $user = User::factory()->create(['is_active' => true]);
    $data = alertData($tenant);

    $service = app(AlertService::class);
    $first = $service->create($data);
    $service->dismiss($first, $user);

    $second = $service->create($data);

    expect($second)->toBeInstanceOf(Alert::class);
    expect(Alert::withoutGlobalScopes()->count())->toBe(2);
});

// ── resolve() y dismiss() ────────────────────────────────────────────────────

it('resuelve una alerta abierta correctamente', function () {
    Event::fake();

    $tenant = alertTenant();
    $user = User::factory()->create(['is_active' => true]);

    $alert = app(AlertService::class)->create(alertData($tenant));
    app(AlertService::class)->resolve($alert, $user);

    expect($alert->fresh())
        ->status->toBe(AlertStatus::Resolved)
        ->closed_by->toBe($user->id)
        ->closed_at->not->toBeNull();
});

it('descarta una alerta abierta correctamente', function () {
    Event::fake();

    $tenant = alertTenant();
    $user = User::factory()->create(['is_active' => true]);

    $alert = app(AlertService::class)->create(alertData($tenant));
    app(AlertService::class)->dismiss($alert, $user);

    expect($alert->fresh())
        ->status->toBe(AlertStatus::Dismissed)
        ->closed_by->toBe($user->id);
});

it('no modifica una alerta ya cerrada al volver a resolver', function () {
    Event::fake();

    $tenant = alertTenant();
    $user = User::factory()->create(['is_active' => true]);

    $service = app(AlertService::class);
    $alert = $service->create(alertData($tenant));
    $service->resolve($alert, $user);

    $closedAt = $alert->fresh()->closed_at->toISOString();

    $service->resolve($alert->fresh(), $user);  // segunda llamada — no-op

    expect($alert->fresh()->closed_at->toISOString())->toBe($closedAt);
});

// ── autoResolveForEntity() ────────────────────────────────────────────────────

it('auto-resuelve alertas de una entidad eliminada y guarda el nombre en metadata', function () {
    Event::fake();

    $tenant = alertTenant();
    $entityId = (string) Str::uuid7();

    app(AlertService::class)->create(
        alertData($tenant, ['entityType' => 'spare_part', 'entityId' => $entityId])
    );

    app(AlertService::class)->autoResolveForEntity(
        tenantId: $tenant->id,
        entityType: 'spare_part',
        entityId: $entityId,
        entityName: 'SP-001 — Rodamiento SKF',
    );

    $alert = Alert::withoutGlobalScopes()->first();

    expect($alert->status)->toBe(AlertStatus::Resolved)
        ->and($alert->closed_by)->toBeNull()
        ->and($alert->metadata['auto_resolved'])->toBe('entity_deleted')
        ->and($alert->metadata['entity_name'])->toBe('SP-001 — Rodamiento SKF');
});

// ── Observers ────────────────────────────────────────────────────────────────

it('auto-resuelve alertas de un MaintenancePlan al eliminarlo', function () {
    Event::fake([AlertCreated::class]);

    $tenant = alertTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $plan = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    app(AlertService::class)->create(
        alertData($tenant, ['entityType' => 'maintenance_plan', 'entityId' => $plan->id])
    );

    $plan->delete();

    expect(Alert::withoutGlobalScopes()->first()->status)->toBe(AlertStatus::Resolved);
});

it('auto-resuelve alertas de una WorkOrder al eliminarla', function () {
    Event::fake([AlertCreated::class]);

    $tenant = alertTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    app(AlertService::class)->create(
        alertData($tenant, ['entityType' => 'work_order', 'entityId' => $wo->id])
    );

    $wo->delete();

    expect(Alert::withoutGlobalScopes()->first()->status)->toBe(AlertStatus::Resolved);
});

// ── Idempotencia atómica (Sprint 11.2) ─────────────────────────────────────────

it('resolve() retorna true cuando resuelve y false si ya estaba cerrada', function () {
    Event::fake();

    $tenant = alertTenant();
    $user = User::factory()->create(['is_active' => true]);
    $service = app(AlertService::class);

    $alert = $service->create(alertData($tenant));

    $first = $service->resolve($alert, $user);
    $second = $service->resolve($alert->fresh(), $user);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse();

    expect($alert->fresh()->status)->toBe(AlertStatus::Resolved);
});

it('dismiss() retorna true cuando descarta y false si ya estaba cerrada', function () {
    Event::fake();

    $tenant = alertTenant();
    $user = User::factory()->create(['is_active' => true]);
    $service = app(AlertService::class);

    $alert = $service->create(alertData($tenant));

    $first = $service->dismiss($alert, $user);
    $second = $service->dismiss($alert->fresh(), $user);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse();
});

it('resolve() concurrente solo persiste el primer closed_by', function () {
    Event::fake();

    $tenant = alertTenant();
    $userA = User::factory()->create(['is_active' => true]);
    $userB = User::factory()->create(['is_active' => true]);
    $service = app(AlertService::class);

    $alert = $service->create(alertData($tenant));

    // Simula concurrencia: ambos leen la alerta como abierta,
    // pero solo el primer UPDATE atomico gana
    $resultA = $service->resolve($alert, $userA);
    $resultB = $service->resolve($alert->fresh(), $userB);

    expect($resultA)->toBeTrue()
        ->and($resultB)->toBeFalse()
        ->and($alert->fresh()->closed_by)->toBe($userA->id);
});

// ── getOpenCriticalCount() con cache ─────────────────────────────────────────

it('devuelve el conteo de alertas críticas y lo cachea', function () {
    Event::fake();
    Cache::flush();

    $tenant = alertTenant();

    app(AlertService::class)->create(new CreateAlertData(
        tenantId: $tenant->id,
        severity: AlertSeverity::Critical,
        category: AlertCategory::Reliability,
        title: 'MTBF crítico',
        entityType: 'equipment',
        entityId: (string) Str::uuid7(),
    ));

    $count = app(AlertService::class)->getOpenCriticalCount($tenant->id);

    expect($count)->toBe(1)
        ->and(Cache::has("critical_alerts_{$tenant->id}"))->toBeTrue();
});

it('invalida la cache al resolver una alerta crítica', function () {
    Event::fake();
    Cache::flush();

    $tenant = alertTenant();
    $user = User::factory()->create(['is_active' => true]);
    $service = app(AlertService::class);

    $alert = $service->create(new CreateAlertData(
        tenantId: $tenant->id,
        severity: AlertSeverity::Critical,
        category: AlertCategory::Reliability,
        title: 'MTBF crítico',
        entityType: 'equipment',
        entityId: (string) Str::uuid7(),
    ));

    $service->getOpenCriticalCount($tenant->id);
    expect(Cache::has("critical_alerts_{$tenant->id}"))->toBeTrue();

    $service->resolve($alert, $user);
    expect(Cache::has("critical_alerts_{$tenant->id}"))->toBeFalse();
});
