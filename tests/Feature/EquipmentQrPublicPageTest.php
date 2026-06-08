<?php

use App\Models\Equipment;
use App\Models\EquipmentQrCode;
use Illuminate\Support\Str;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create an active QR code for an equipment, bypassing global scopes.
 *
 * @return array{Equipment, EquipmentQrCode}
 */
function makeActiveQr(): array
{
    $equipment = Equipment::factory()->create();

    $qrCode = EquipmentQrCode::withoutGlobalScopes()->create([
        'equipment_id'  => $equipment->id,
        'tenant_id'     => $equipment->tenant_id,
        'qr_token'      => (string) Str::uuid(),
        'qr_image_path' => null,
        'is_active'     => true,
        'generated_at'  => now(),
        'scan_count'    => 0,
    ]);

    return [$equipment, $qrCode];
}

// ── Public page access ────────────────────────────────────────────────────────

it('shows equipment public profile for a valid active token', function () {
    [$equipment, $qrCode] = makeActiveQr();

    $response = $this->get(route('equipment.qr.show', $qrCode->qr_token));

    $response->assertOk();
    $response->assertSee($equipment->code);
    $response->assertSee($equipment->name);
});

it('returns 404 view for an unknown token', function () {
    $response = $this->get(route('equipment.qr.show', Str::uuid()));

    $response->assertStatus(404);
    $response->assertSee('Código QR no encontrado');
});

it('returns 404 for an inactive qr code', function () {
    $equipment = Equipment::factory()->create();

    $qrCode = EquipmentQrCode::withoutGlobalScopes()->create([
        'equipment_id'  => $equipment->id,
        'tenant_id'     => $equipment->tenant_id,
        'qr_token'      => (string) Str::uuid(),
        'qr_image_path' => null,
        'is_active'     => false,
        'generated_at'  => now(),
        'scan_count'    => 0,
    ]);

    $this->get(route('equipment.qr.show', $qrCode->qr_token))
        ->assertStatus(404);
});

it('returns 404 for a soft-deleted qr code', function () {
    [$equipment, $qrCode] = makeActiveQr();

    $qrCode->delete();

    $this->get(route('equipment.qr.show', $qrCode->qr_token))
        ->assertStatus(404);
});

// ── Scan tracking ─────────────────────────────────────────────────────────────

it('increments the scan count on each visit', function () {
    [$equipment, $qrCode] = makeActiveQr();

    $this->get(route('equipment.qr.show', $qrCode->qr_token));

    expect($qrCode->fresh()->scan_count)->toBe(1);
});

it('records last_scanned_at on visit', function () {
    [$equipment, $qrCode] = makeActiveQr();

    $this->get(route('equipment.qr.show', $qrCode->qr_token));

    expect($qrCode->fresh()->last_scanned_at)->not->toBeNull();
});

// ── Public page content ───────────────────────────────────────────────────────

it('shows equipment status on public profile', function () {
    [$equipment, $qrCode] = makeActiveQr();

    $this->get(route('equipment.qr.show', $qrCode->qr_token))
        ->assertSee($equipment->status->label());
});

it('shows equipment criticality on public profile', function () {
    [$equipment, $qrCode] = makeActiveQr();

    $this->get(route('equipment.qr.show', $qrCode->qr_token))
        ->assertSee($equipment->criticality->label());
});
