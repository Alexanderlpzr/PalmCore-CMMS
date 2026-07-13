<?php

use App\Domain\Assets\Enums\StoppageCategory;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;

// ── Helpers ───────────────────────────────────────────────────────────────────

function downtimeActor(Tenant $tenant, array $abilities = ['*']): string
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('test-token', $abilities);
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return $token->plainTextToken;
}

function downtimeHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
    $this->token = downtimeActor($this->tenant);
});

// ── Registrar ─────────────────────────────────────────────────────────────────

it('registers a plant-wide stoppage with no equipment and no work order', function (): void {
    $this->withHeaders(downtimeHeaders($this->token))
        ->postJson('/api/v1/downtime-events', [
            'plant_id' => $this->plant->id,
            'stoppage_category' => StoppageCategory::RawMaterial->value,
            'stoppage_cause' => 'No llegó fruta',
            'started_at' => now()->subHours(3)->toISOString(),
            'ended_at' => now()->subHour()->toISOString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_plant_wide', true)
        ->assertJsonPath('data.duration_minutes', 120)
        ->assertJsonPath('data.stoppage_category_label', 'Falta de fruta')
        ->assertJsonPath('data.is_maintenance_responsibility', false)
        ->assertJsonPath('data.work_order_id', null)
        ->assertJsonPath('data.source', 'manual');
});

it('opens an ongoing stoppage when no end time is given', function (): void {
    $this->withHeaders(downtimeHeaders($this->token))
        ->postJson('/api/v1/downtime-events', [
            'equipment_id' => $this->equipment->id,
            'stoppage_category' => StoppageCategory::Mechanical->value,
            'stoppage_cause' => 'Rodamiento prensa 2',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_ongoing', true)
        ->assertJsonPath('data.duration_minutes', null)
        // The plant is derived from the equipment: nobody should have to type it.
        ->assertJsonPath('data.plant_id', $this->plant->id);
});

it('demands a Tipo I classification', function (): void {
    $this->withHeaders(downtimeHeaders($this->token))
        ->postJson('/api/v1/downtime-events', ['equipment_id' => $this->equipment->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.stoppage_category.0', 'Un paro debe clasificarse (Tipo I).');
});

it('demands an equipment or a plant', function (): void {
    $this->withHeaders(downtimeHeaders($this->token))
        ->postJson('/api/v1/downtime-events', [
            'stoppage_category' => StoppageCategory::Process->value,
        ])
        ->assertStatus(422);
});

it('refuses to open a second stoppage on an equipment already down', function (): void {
    $payload = [
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical->value,
    ];

    $this->withHeaders(downtimeHeaders($this->token))
        ->postJson('/api/v1/downtime-events', $payload)
        ->assertCreated();

    $this->withHeaders(downtimeHeaders($this->token))
        ->postJson('/api/v1/downtime-events', $payload)
        ->assertStatus(409);
});

it('refuses a token without the downtime write ability', function (): void {
    $token = downtimeActor($this->tenant, ['downtime.read']);

    $this->withHeaders(downtimeHeaders($token))
        ->postJson('/api/v1/downtime-events', [
            'plant_id' => $this->plant->id,
            'stoppage_category' => StoppageCategory::Process->value,
        ])
        ->assertForbidden();
});

// ── Cerrar ────────────────────────────────────────────────────────────────────

it('closes an open stoppage', function (): void {
    $event = EquipmentDowntimeEvent::factory()->ongoing()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'started_at' => now()->subMinutes(90),
    ]);

    $this->withHeaders(downtimeHeaders($this->token))
        ->patchJson("/api/v1/downtime-events/{$event->id}/end", [
            'ended_at' => now()->toISOString(),
            'notes' => 'Se reemplazó el rodamiento',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_ongoing', false)
        ->assertJsonPath('data.duration_minutes', 90);
});

it('refuses to close a stoppage twice', function (): void {
    $event = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $this->withHeaders(downtimeHeaders($this->token))
        ->patchJson("/api/v1/downtime-events/{$event->id}/end")
        ->assertStatus(409);
});

// ── Lo que se pregunta cada lunes ────────────────────────────────────────────

it('returns the lost production hours split by Tipo I, worst first', function (): void {
    // Paros consecutivos: la planta no puede estar parada dos veces a la vez.
    $cursor = now()->startOfMonth()->addDays(2);

    $register = function (StoppageCategory $category, int $hours) use (&$cursor) {
        $startedAt = $cursor->copy();
        $cursor = $startedAt->copy()->addHours($hours);

        $this->withHeaders(downtimeHeaders($this->token))
            ->postJson('/api/v1/downtime-events', [
                'plant_id' => $this->plant->id,
                'stoppage_category' => $category->value,
                'started_at' => $startedAt->toISOString(),
                'ended_at' => $cursor->toISOString(),
            ])->assertCreated();
    };

    $register(StoppageCategory::Mechanical, 2);
    $register(StoppageCategory::RawMaterial, 6);

    $this->withHeaders(downtimeHeaders($this->token))
        ->getJson("/api/v1/plants/{$this->plant->id}/lost-hours")
        ->assertOk()
        ->assertJsonPath('data.total_hours', 8)
        ->assertJsonPath('data.by_category.0.category', 'raw_material')
        ->assertJsonPath('data.by_category.0.hours', 6)
        ->assertJsonPath('data.by_category.0.is_maintenance_responsibility', false)
        ->assertJsonPath('data.by_category.1.category', 'mechanical')
        ->assertJsonPath('data.by_category.1.hours', 2);
});

it('lists only the ongoing stoppages when asked', function (): void {
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
    ]);
    EquipmentDowntimeEvent::factory()->ongoing()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => Equipment::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ]);

    $this->withHeaders(downtimeHeaders($this->token))
        ->getJson('/api/v1/downtime-events?ongoing=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_ongoing', true);
});

// ── Multi-tenant ──────────────────────────────────────────────────────────────

it('never lets one tenant close another tenant stoppage', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);
    $event = EquipmentDowntimeEvent::factory()->ongoing()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'equipment_id' => Equipment::factory()->create(['tenant_id' => $other->id])->id,
    ]);

    $this->withHeaders(downtimeHeaders($this->token))
        ->patchJson("/api/v1/downtime-events/{$event->id}/end")
        ->assertNotFound();
});
