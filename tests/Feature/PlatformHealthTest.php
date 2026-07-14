<?php

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Platform\Enums\HealthStatus;
use App\Domain\Platform\Services\SystemHealthService;
use App\Domain\Platform\Services\TenantAccessService;
use App\Domain\Platform\Services\TenantHealthService;
use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La sala de máquinas.
 *
 * Un panel de salud que inventa buenas noticias es peor que no tener panel: te deja
 * dormir tranquilo mientras el sistema se apaga. Lo que se prueba aquí es que dice la
 * verdad, y que cuando no la sabe lo admite en vez de pintarlo de verde.
 */
beforeEach(function (): void {
    $this->health = app(SystemHealthService::class);
});

// ── Salud del sistema ────────────────────────────────────────────────────────

it('reports the scheduler as dead when its heartbeat goes cold', function (): void {
    // El scheduler no falla con un error: deja de correr y nadie se entera. De él
    // dependen los preventivos, el cierre del mes y las alertas de horómetro.
    Cache::put('platform.scheduler.heartbeat', now()->subHour()->toISOString());

    $scheduler = collect($this->health->checks())->firstWhere('key', 'scheduler');

    expect($scheduler['status'])->toBe(HealthStatus::Critical)
        ->and($scheduler['detail'])->toContain('preventivos');
});

it('says it does not know instead of saying everything is fine', function (): void {
    // Sin latido no se puede afirmar que el scheduler esté sano. Tampoco que esté roto.
    Cache::forget('platform.scheduler.heartbeat');

    $scheduler = collect($this->health->checks())->firstWhere('key', 'scheduler');

    expect($scheduler['status'])->toBe(HealthStatus::Unknown)
        ->and($scheduler['status'])->not->toBe(HealthStatus::Ok);
});

it('reports the scheduler as healthy while it keeps beating', function (): void {
    Cache::put('platform.scheduler.heartbeat', now()->subMinutes(3)->toISOString());

    expect(collect($this->health->checks())->firstWhere('key', 'scheduler')['status'])
        ->toBe(HealthStatus::Ok);
});

it('raises a critical check when a job failed in the last day', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'maintenance',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\GeneratePreventiveWorkOrdersJob']),
        'exception' => 'RuntimeException: se cayó',
        'failed_at' => now(),
    ]);

    $check = collect($this->health->checks())->firstWhere('key', 'failed_jobs');

    expect($check['status'])->toBe(HealthStatus::Critical)
        ->and($check['value'])->toBe('1');
});

it('knows which queues Horizon actually watches', function (): void {
    // El bug que dio origen a este panel: el generador de preventivos escribía a la
    // cola «maintenance» y ningún supervisor la atendía. Los jobs se quedaban en Redis
    // para siempre, sin error y sin log.
    expect($this->health->supervisedQueues())
        ->toContain('maintenance')
        ->toContain('analytics')
        ->toContain('default');
});

it('does not claim anything about queues when they do not run on Redis', function (): void {
    config()->set('queue.default', 'sync');

    // Sin Redis no se puede saber cuántos trabajos esperan. No se inventa un cero.
    expect($this->health->queuesWithPendingJobs())->toBe([]);

    expect(collect($this->health->checks())->firstWhere('key', 'orphan_queues')['status'])
        ->toBe(HealthStatus::Ok);
});

it('surfaces the worst status of them all', function (): void {
    Cache::put('platform.scheduler.heartbeat', now()->subHour()->toISOString());

    // Un solo chequeo crítico tiñe el semáforo entero: es el punto del semáforo.
    expect($this->health->overallStatus())->toBe(HealthStatus::Critical);
});

// ── Salud por empresa ────────────────────────────────────────────────────────

it('counts what the plant is actually doing', function (): void {
    $tenant = Tenant::factory()->create();
    $plant = Plant::factory()->create(['tenant_id' => $tenant->id]);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'plant_id' => $plant->id]);

    $user = User::factory()->create(['last_login_at' => now()->subDay()]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::InProgress->value,
        'planned_end_at' => now()->subWeek(),
    ]);

    Alert::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'severity' => AlertSeverity::Critical->value,
        'category' => AlertCategory::Reliability->value,
        'title' => 'Falla crítica',
        'status' => AlertStatus::Open->value,
    ]);

    $row = app(TenantHealthService::class)->forTenant($tenant);

    expect($row['users'])->toBe(1)
        ->and($row['active_users'])->toBe(1)
        ->and($row['equipment'])->toBe(1)
        ->and($row['open_work_orders'])->toBe(1)
        // La OT que se pasó de su fecha y sigue abierta: la deuda real de la planta.
        ->and($row['overdue_work_orders'])->toBe(1)
        ->and($row['critical_alerts'])->toBe(1)
        ->and($row['is_dormant'])->toBeFalse();
});

it('flags the company where nobody has logged in for weeks', function (): void {
    // Un CMMS no muere con un error: muere el mes en que nadie vuelve a entrar.
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['last_login_at' => now()->subMonth()]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $row = app(TenantHealthService::class)->forTenant($tenant);

    expect($row['is_dormant'])->toBeTrue()
        ->and($row['active_users'])->toBe(0)
        ->and($row['days_since_activity'])->toBeGreaterThanOrEqual(28);
});

it('does not confuse a company nobody ever used with an active one', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['last_login_at' => null]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $row = app(TenantHealthService::class)->forTenant($tenant);

    expect($row['last_activity_at'])->toBeNull()
        ->and($row['is_dormant'])->toBeTrue();
});

it('never counts another tenant work orders as its own', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'status' => WorkOrderStatus::InProgress->value,
    ]);

    expect(app(TenantHealthService::class)->forTenant($tenant)['open_work_orders'])->toBe(0);
});

// ── Control: suspender y devolver el acceso ──────────────────────────────────

it('suspends a company without touching a single row of its history', function (): void {
    $tenant = Tenant::factory()->create(['subscription_status' => SubscriptionStatus::Active->value]);
    $plant = Plant::factory()->create(['tenant_id' => $tenant->id]);
    Equipment::factory()->count(2)->create(['tenant_id' => $tenant->id, 'plant_id' => $plant->id]);

    app(TenantAccessService::class)->suspend($tenant);

    expect($tenant->refresh()->subscription_status)->toBe(SubscriptionStatus::Suspended)
        ->and($tenant->is_active)->toBeFalse()
        // Suspender es cerrar la llave, no demoler la casa.
        ->and(Equipment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(2);
});

it('gives the company back exactly as it was', function (): void {
    $tenant = Tenant::factory()->create(['subscription_status' => SubscriptionStatus::Suspended->value, 'is_active' => false]);

    app(TenantAccessService::class)->reactivate($tenant);

    expect($tenant->refresh()->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($tenant->is_active)->toBeTrue();
});

it('refuses to suspend a company that is already suspended', function (): void {
    $tenant = Tenant::factory()->create(['subscription_status' => SubscriptionStatus::Suspended->value]);

    expect(fn () => app(TenantAccessService::class)->suspend($tenant))
        ->toThrow(BusinessRuleException::class);
});

it('does not guess an owner when the company has none', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['joined_at' => now(), 'is_owner' => false]);

    expect(app(TenantAccessService::class)->owner($tenant))->toBeNull();
});
