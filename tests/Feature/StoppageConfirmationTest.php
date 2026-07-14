<?php

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageConfirmationStatus;
use App\Domain\Assets\Services\DowntimeService;
use App\Exceptions\BusinessRuleException;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A5 — la firma de producción sobre el paro.
 *
 * Las horas perdidas se le restan a la planta, no a mantenimiento, pero hasta hoy
 * las declaraba mantenimiento solo: el mismo que sale mal en la foto es el que
 * escribe el número. El jefe de turno firma que la planta estuvo abajo — o deja
 * constancia de que no está de acuerdo, sin que el paro desaparezca del informe.
 */
beforeEach(function (): void {
    $this->downtime = app(DowntimeService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
    $this->shiftLead = User::factory()->create();
});

function confirmHeaders(Tenant $tenant, array $abilities = ['downtime.write']): array
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('test-token', $abilities);
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

/** @param array<string, mixed> $attributes */
function closedStoppage(array $attributes = []): EquipmentDowntimeEvent
{
    return EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'equipment_id' => test()->equipment->id,
        'started_at' => Carbon::parse('2026-06-10 08:00:00'),
        'ended_at' => Carbon::parse('2026-06-10 11:00:00'),
        'duration_minutes' => 180,
        'affects_production' => true,
        'stoppage_category' => StoppageCategory::Mechanical->value,
        ...$attributes,
    ]);
}

// ── Firmar ───────────────────────────────────────────────────────────────────

it('records who signed the hours and when', function (): void {
    $paro = closedStoppage();

    expect($paro->confirmation_status)->toBe(StoppageConfirmationStatus::Pending);

    $this->downtime->confirm($paro, $this->shiftLead, 'Coincide con la bitácora del turno 2.');

    expect($paro->refresh()->confirmation_status)->toBe(StoppageConfirmationStatus::Confirmed)
        ->and($paro->confirmed_by)->toBe($this->shiftLead->id)
        ->and($paro->confirmed_at)->not->toBeNull()
        ->and($paro->confirmation_notes)->toBe('Coincide con la bitácora del turno 2.');
});

it('keeps a disputed paro in the numbers instead of erasing it', function (): void {
    $paro = closedStoppage();

    $this->downtime->dispute($paro, $this->shiftLead, 'La línea reanudó a las 10:15, no a las 11:00.');

    $paro->refresh();

    expect($paro->confirmation_status)->toBe(StoppageConfirmationStatus::Disputed)
        ->and($paro->confirmation_notes)->toBe('La línea reanudó a las 10:15, no a las 11:00.')
        // El desacuerdo no borra las horas: sigue contando hasta que las dos áreas
        // se sienten a mirarlo. Un paro en disputa que desaparece del reporte es la
        // mentira que este campo existe para evitar.
        ->and($paro->duration_minutes)->toBe(180)
        ->and($paro->affects_production)->toBeTrue();
});

it('refuses to dispute without a reason', function (): void {
    $paro = closedStoppage();

    expect(fn () => $this->downtime->dispute($paro, $this->shiftLead, '   '))
        ->toThrow(BusinessRuleException::class);
});

// ── Lo que no se firma ───────────────────────────────────────────────────────

it('refuses to sign a paro that is still open', function (): void {
    // Mientras la planta sigue abajo no hay horas que firmar: todavía están corriendo.
    $paro = EquipmentDowntimeEvent::factory()->ongoing()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    expect(fn () => $this->downtime->confirm($paro, $this->shiftLead))
        ->toThrow(BusinessRuleException::class);
});

it('refuses to sign a failure that never cost production a single hour', function (): void {
    $paro = closedStoppage(['affects_production' => false]);

    expect(fn () => $this->downtime->confirm($paro, $this->shiftLead))
        ->toThrow(BusinessRuleException::class);
});

it('refuses to sign the same paro twice', function (): void {
    $paro = closedStoppage();

    $this->downtime->confirm($paro, $this->shiftLead);

    // La firma es un hecho fechado, no un campo editable.
    expect(fn () => $this->downtime->dispute($paro->refresh(), User::factory()->create(), 'Me arrepentí'))
        ->toThrow(BusinessRuleException::class);
});

// ── Cuánto del informe va sin firmar ─────────────────────────────────────────

it('says how much of the month nobody from production has signed', function (): void {
    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-30 23:59:59');

    closedStoppage(); // 3 h sin firmar
    $signed = closedStoppage([
        'equipment_id' => null, // paro de planta: no compite por el reloj del equipo
        'started_at' => Carbon::parse('2026-06-12 08:00:00'),
        'ended_at' => Carbon::parse('2026-06-12 10:00:00'),
        'duration_minutes' => 120,
    ]);
    $this->downtime->confirm($signed, $this->shiftLead);

    $pending = $this->downtime->pendingConfirmation($this->plant->id, $from, $to);

    expect($pending['events'])->toBe(1)
        ->and($pending['hours'])->toBe(3.0);
});

it('does not count a paro that is still running as pending signature', function (): void {
    EquipmentDowntimeEvent::factory()->ongoing()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $pending = $this->downtime->pendingConfirmation(
        $this->plant->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30 23:59:59'),
    );

    // Todavía no hay horas: pedir su firma sería pedir que firme un número que no existe.
    expect($pending['events'])->toBe(0)
        ->and($pending['hours'])->toBe(0.0);
});

// ── API ──────────────────────────────────────────────────────────────────────

it('signs a paro through the API', function (): void {
    $paro = closedStoppage();

    $this->withHeaders(confirmHeaders($this->tenant))
        ->patchJson("/api/v1/downtime-events/{$paro->id}/confirm", ['notes' => 'De acuerdo.'])
        ->assertOk()
        ->assertJsonPath('data.confirmation_status', 'confirmed')
        ->assertJsonPath('data.confirmed_by.id', fn (?string $id): bool => $id !== null);
});

it('rejects a dispute with no reason through the API', function (): void {
    $paro = closedStoppage();

    $this->withHeaders(confirmHeaders($this->tenant))
        ->patchJson("/api/v1/downtime-events/{$paro->id}/dispute", ['reason' => ''])
        ->assertStatus(422);

    expect($paro->refresh()->confirmation_status)->toBe(StoppageConfirmationStatus::Pending);
});

it('cannot sign a paro from another tenant', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    $foreign = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'equipment_id' => Equipment::factory()->create([
            'tenant_id' => $other->id,
            'plant_id' => $otherPlant->id,
        ])->id,
        'affects_production' => true,
    ]);

    $this->withHeaders(confirmHeaders($this->tenant))
        ->patchJson("/api/v1/downtime-events/{$foreign->id}/confirm")
        ->assertNotFound();

    expect($foreign->refresh()->confirmation_status)->toBe(StoppageConfirmationStatus::Pending);
});
