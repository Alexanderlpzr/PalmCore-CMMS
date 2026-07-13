<?php

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;

// ── Helpers ───────────────────────────────────────────────────────────────────

function meterActor(Tenant $tenant, array $abilities = ['*']): string
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('test-token', $abilities);
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return $token->plainTextToken;
}

function meterHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $this->token = meterActor($this->tenant);
});

// ── Horómetros ────────────────────────────────────────────────────────────────

it('records a single meter reading', function (): void {
    $this->withHeaders(meterHeaders($this->token))
        ->postJson("/api/v1/equipment/{$this->equipment->id}/meter-readings", [
            'reading_value' => 1_250.5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.reading_value', 1250.5)
        ->assertJsonPath('data.is_reset', false);
});

it('accepts a meter swap through the API instead of rejecting the reading', function (): void {
    $this->withHeaders(meterHeaders($this->token))
        ->postJson("/api/v1/equipment/{$this->equipment->id}/meter-readings", ['reading_value' => 10_452])
        ->assertCreated();

    $this->withHeaders(meterHeaders($this->token))
        ->postJson("/api/v1/equipment/{$this->equipment->id}/meter-readings", [
            'reading_value' => 158,
            'notes' => 'Cambio de horómetro',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_reset', true)
        ->assertJsonPath('data.previous_value', 10452)
        ->assertJsonPath('data.delta', 158);
});

it('rejects a negative reading with a readable message', function (): void {
    $this->withHeaders(meterHeaders($this->token))
        ->postJson("/api/v1/equipment/{$this->equipment->id}/meter-readings", ['reading_value' => -3])
        ->assertStatus(422)
        ->assertJsonPath('errors.reading_value.0', 'Una lectura de horómetro no puede ser negativa.');
});

it('records the whole daily round and names only the dials that failed', function (): void {
    $second = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->withHeaders(meterHeaders($this->token))
        ->postJson('/api/v1/meter-readings/bulk', [
            'readings' => [
                ['equipment_id' => $this->equipment->id, 'reading_value' => 1_200],
                ['equipment_id' => $second->id, 'reading_value' => 3_400],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('meta.recorded', 2)
        ->assertJsonPath('meta.failed', []);

    expect($this->equipment->refresh()->current_meter_reading)->toBe(1200.0);
});

it('returns the reading history with the current pace of consumption', function (): void {
    $meters = app(EquipmentMeterReadingService::class);
    $user = User::factory()->create();
    $meters->record($this->equipment, 1_000, $user, recordedAt: now()->subDays(10));
    $meters->record($this->equipment->refresh(), 1_200, $user, recordedAt: now());

    $this->withHeaders(meterHeaders($this->token))
        ->getJson("/api/v1/equipment/{$this->equipment->id}/meter-readings")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_reading', 1200)
        ->assertJsonPath('meta.accumulated_reading', 200)
        ->assertJsonPath('meta.consumption_per_day', 20);
});

it('projects the days left until each plan of the equipment falls due', function (): void {
    $meters = app(EquipmentMeterReadingService::class);
    $user = User::factory()->create();
    $meters->record($this->equipment, 1_000, $user, recordedAt: now()->subDays(10));
    $meters->record($this->equipment->refresh(), 1_200, $user, recordedAt: now()); // 20 h/día

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_meter' => 500,
    ]);

    // Acumulado 200 h, faltan 300 h, ritmo 20 h/día → 15 días.
    $this->withHeaders(meterHeaders($this->token))
        ->getJson("/api/v1/equipment/{$this->equipment->id}/meter-projection")
        ->assertOk()
        ->assertJsonPath('data.0.days_until_due', 15)
        ->assertJsonPath('meta.consumption_per_day', 20);
});

it('refuses a meter reading from a token without equipment write', function (): void {
    $token = meterActor($this->tenant, ['equipment.read']);

    $this->withHeaders(meterHeaders($token))
        ->postJson("/api/v1/equipment/{$this->equipment->id}/meter-readings", ['reading_value' => 10])
        ->assertForbidden();
});

// ── Calendario de producción + eficiencia de planta ──────────────────────────

it('saves the production calendar the planner fills in', function (): void {
    $this->withHeaders(meterHeaders($this->token))
        ->putJson("/api/v1/plants/{$this->plant->id}/production-calendar", [
            'days' => [
                ['calendar_date' => now()->startOfMonth()->toDateString(), 'programmed_hours' => 22.6],
                ['calendar_date' => now()->startOfMonth()->addDay()->toDateString(), 'programmed_hours' => 0, 'notes' => 'Domingo sin fruta'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('meta.saved', 2);

    $this->withHeaders(meterHeaders($this->token))
        ->getJson("/api/v1/plants/{$this->plant->id}/production-calendar")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.programmed_hours', 22.6);
});

it('corrects a day instead of duplicating it', function (): void {
    $day = ['calendar_date' => now()->startOfMonth()->toDateString(), 'programmed_hours' => 20];

    $this->withHeaders(meterHeaders($this->token))
        ->putJson("/api/v1/plants/{$this->plant->id}/production-calendar", ['days' => [$day]])
        ->assertOk();

    $this->withHeaders(meterHeaders($this->token))
        ->putJson("/api/v1/plants/{$this->plant->id}/production-calendar", [
            'days' => [[...$day, 'programmed_hours' => 18]],
        ])
        ->assertOk();

    expect(ProductionCalendarDay::withoutGlobalScopes()->count())->toBe(1)
        ->and(ProductionCalendarDay::withoutGlobalScopes()->first()->programmed_hours)->toBe(18.0);
});

it('rejects a day with more than 24 programmed hours', function (): void {
    $this->withHeaders(meterHeaders($this->token))
        ->putJson("/api/v1/plants/{$this->plant->id}/production-calendar", [
            'days' => [['calendar_date' => now()->toDateString(), 'programmed_hours' => 30]],
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['Un día no puede tener más de 24 horas programadas.']);
});

it('serves the plant efficiency the client reports by hand', function (): void {
    // 452 h programadas − 38,6 h perdidas = 413,4 h efectivas = 91,46 %.
    $start = now()->startOfMonth();
    for ($i = 0; $i < 20; $i++) {
        ProductionCalendarDay::create([
            'tenant_id' => $this->tenant->id,
            'plant_id' => $this->plant->id,
            'calendar_date' => $start->copy()->addDays($i)->toDateString(),
            'programmed_hours' => 22.6,
        ]);
    }

    app(DowntimeService::class)->register([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => $start->copy()->addDays(2),
        'ended_at' => $start->copy()->addDays(2)->addMinutes(2316), // 38,6 h
    ], User::factory()->create());

    $this->withHeaders(meterHeaders($this->token))
        ->getJson("/api/v1/plants/{$this->plant->id}/kpis")
        ->assertOk()
        ->assertJsonPath('data.programmed_hours', 452)
        ->assertJsonPath('data.effective_hours', 413.4)
        ->assertJsonPath('data.efficiency_percentage', 91.46)
        ->assertJsonPath('data.failure_count', 1);
});

it('reports a null efficiency when the month was never programmed', function (): void {
    $this->withHeaders(meterHeaders($this->token))
        ->getJson("/api/v1/plants/{$this->plant->id}/kpis")
        ->assertOk()
        ->assertJsonPath('data.programmed_hours', 0)
        ->assertJsonPath('data.efficiency_percentage', null);
});

it('never serves the KPIs of another tenant plant', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    $this->withHeaders(meterHeaders($this->token))
        ->getJson("/api/v1/plants/{$otherPlant->id}/kpis")
        ->assertNotFound();
});
