<?php

use App\Domain\Assets\Services\QrCodeService;
use App\Jobs\GenerateEquipmentQrCode;
use App\Models\Equipment;
use App\Models\EquipmentQrCode;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

// ── QrCodeService ─────────────────────────────────────────────────────────────

it('creates a qr code record for equipment with correct fields', function () {
    Storage::fake('public');

    $equipment = Equipment::factory()->create();

    /** @var QrCodeService $service */
    $service = app(QrCodeService::class);
    $qrCode  = $service->createForEquipment($equipment);

    expect($qrCode->equipment_id)->toBe($equipment->id)
        ->and($qrCode->tenant_id)->toBe($equipment->tenant_id)
        ->and($qrCode->is_active)->toBeTrue()
        ->and($qrCode->scan_count)->toBe(0)
        ->and($qrCode->qr_token)->toBeString()->toHaveLength(36) // UUID length
        ->and($qrCode->qr_image_path)->not->toBeNull();
});

it('stores the qr image as a valid png file', function () {
    Storage::fake('public');

    $equipment = Equipment::factory()->create();

    /** @var QrCodeService $service */
    $service = app(QrCodeService::class);
    $qrCode  = $service->createForEquipment($equipment);

    Storage::disk('public')->assertExists($qrCode->qr_image_path);
});

it('builds public url using the qr token', function () {
    /** @var QrCodeService $service */
    $service = app(QrCodeService::class);
    $token   = $service->generateToken();

    expect($service->buildPublicUrl($token))
        ->toBe(route('equipment.qr.show', $token));
});

it('generates unique tokens on each call', function () {
    /** @var QrCodeService $service */
    $service = app(QrCodeService::class);

    $tokens = collect(range(1, 10))->map(fn () => $service->generateToken());

    expect($tokens->unique())->toHaveCount(10);
});

it('regenerates qr code: old is soft-deleted, new is active', function () {
    Storage::fake('public');

    $equipment = Equipment::factory()->create();

    /** @var QrCodeService $service */
    $service = app(QrCodeService::class);
    $old     = $service->createForEquipment($equipment);
    $oldId   = $old->id;
    $oldPath = $old->qr_image_path;

    $new = $service->regenerate($old);

    // Old record is soft-deleted and inactive
    $deletedOld = EquipmentQrCode::withoutGlobalScopes()->withTrashed()->find($oldId);
    expect($deletedOld->is_active)->toBeFalse()
        ->and($deletedOld->deleted_at)->not->toBeNull();

    // New record is active with different token
    expect($new->is_active)->toBeTrue()
        ->and($new->qr_token)->not->toBe($deletedOld->qr_token)
        ->and($new->equipment_id)->toBe($equipment->id);
});

it('deletes old image file after regeneration', function () {
    Storage::fake('public');

    $equipment = Equipment::factory()->create();

    /** @var QrCodeService $service */
    $service = app(QrCodeService::class);
    $old     = $service->createForEquipment($equipment);
    $oldPath = $old->qr_image_path;

    $service->regenerate($old);

    Storage::disk('public')->assertMissing($oldPath);
});

// ── Observer dispatch ─────────────────────────────────────────────────────────

it('queues QR generation after response when equipment is created', function () {
    Bus::fake();

    Equipment::factory()->create();

    Bus::assertDispatchedAfterResponse(GenerateEquipmentQrCode::class);
});
