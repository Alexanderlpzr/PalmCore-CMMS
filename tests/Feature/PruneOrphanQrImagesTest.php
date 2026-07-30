<?php

use App\Domain\Assets\Services\QrCodeService;
use App\Models\Equipment;
use Illuminate\Support\Facades\Storage;

// ── qr:prune-orphans ──────────────────────────────────────────────────────────

it('reports orphans without deleting them by default', function () {
    $equipment = Equipment::factory()->create();
    $qrCode = app(QrCodeService::class)->createForEquipment($equipment);

    Storage::disk(persistent_disk())->put('equipment-qr/leaked-tenant/orphan.png', 'fake-png');

    $this->artisan('qr:prune-orphans')
        ->expectsOutputToContain('orphans: 1')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    Storage::disk(persistent_disk())->assertExists('equipment-qr/leaked-tenant/orphan.png');
    Storage::disk(persistent_disk())->assertExists($qrCode->qr_image_path);
});

it('deletes unreferenced images and keeps referenced ones with --force', function () {
    $equipment = Equipment::factory()->create();
    $qrCode = app(QrCodeService::class)->createForEquipment($equipment);

    Storage::disk(persistent_disk())->put('equipment-qr/leaked-tenant/orphan.png', 'fake-png');

    $this->artisan('qr:prune-orphans', ['--force' => true])
        ->expectsOutputToContain('Deleted 1 orphan image(s)')
        ->assertSuccessful();

    Storage::disk(persistent_disk())->assertMissing('equipment-qr/leaked-tenant/orphan.png');
    Storage::disk(persistent_disk())->assertExists($qrCode->qr_image_path);
});

it('keeps images belonging to soft-deleted qr codes so the audit trail survives', function () {
    $equipment = Equipment::factory()->create();

    $original = app(QrCodeService::class)->createForEquipment($equipment);
    $originalPath = $original->qr_image_path;

    // Soft-delete without touching the file, mimicking a retired-but-audited token.
    $original->delete();

    $this->artisan('qr:prune-orphans', ['--force' => true])
        ->expectsOutputToContain('orphans: 0')
        ->assertSuccessful();

    Storage::disk(persistent_disk())->assertExists($originalPath);
});

it('removes tenant directories left empty after pruning', function () {
    Storage::disk(persistent_disk())->put('equipment-qr/empty-tenant/orphan.png', 'fake-png');

    $this->artisan('qr:prune-orphans', ['--force' => true])
        ->assertSuccessful();

    expect(Storage::disk(persistent_disk())->directories('equipment-qr'))->toBe([]);
});

it('succeeds when the prefix does not exist', function () {
    $this->artisan('qr:prune-orphans', ['--prefix' => 'nope'])
        ->expectsOutputToContain('does not exist')
        ->assertSuccessful();
});
