<?php

use Illuminate\Support\Facades\Storage;

/**
 * Production-Readiness-1 — cloud (R2) storage abstraction.
 * All tests run against Storage::fake() — no real Cloudflare dependency.
 */

// ── Disk configuration ────────────────────────────────────────────────────────

it('defaults the persistent disks to local in development', function () {
    expect(persistent_disk())->toBe('public')
        ->and(private_files_disk())->toBe('work_orders_private');
});

it('exposes an r2 disk using the s3 driver', function () {
    expect(config('filesystems.disks.r2.driver'))->toBe('s3')
        ->and(config('filesystems.disks.r2.visibility'))->toBe('private');
});

// ── file_signed_url() ─────────────────────────────────────────────────────────

it('returns null for an empty path', function () {
    expect(file_signed_url('public', null))->toBeNull()
        ->and(file_signed_url('public', ''))->toBeNull();
});

it('returns a plain public url for local disks', function () {
    Storage::fake('public');
    Storage::disk('public')->put('docs/a.pdf', 'x');

    expect(file_signed_url('public', 'docs/a.pdf'))->toContain('docs/a.pdf');
});

it('returns a signed, time-limited url for object-storage disks', function () {
    config(['filesystems.disks.r2.driver' => 's3']);

    $disk = Mockery::mock();
    $disk->shouldReceive('temporaryUrl')->once()->andReturn('https://r2.example/o.pdf?sig=abc&expires=123');
    Storage::shouldReceive('disk')->with('r2')->andReturn($disk);

    expect(file_signed_url('r2', 'o.pdf', 10))->toBe('https://r2.example/o.pdf?sig=abc&expires=123');
});

// ── Upload / download / delete on the persistent disk ─────────────────────────

it('uploads, reads back and deletes a file on the persistent disk', function () {
    Storage::fake('public');
    $disk = Storage::disk(persistent_disk());

    $disk->put('equipment-qr/t1/code.png', 'PNGDATA');
    expect($disk->exists('equipment-qr/t1/code.png'))->toBeTrue()
        ->and($disk->get('equipment-qr/t1/code.png'))->toBe('PNGDATA');

    $disk->delete('equipment-qr/t1/code.png');
    expect($disk->exists('equipment-qr/t1/code.png'))->toBeFalse();
});

// ── storage:migrate-to-cloud ──────────────────────────────────────────────────

it('migrates local files to the cloud disk and verifies checksums', function () {
    config(['filesystems.persistent_disk' => 'r2', 'filesystems.private_disk' => 'r2']);
    Storage::fake('public');
    Storage::fake('work_orders_private');
    Storage::fake('r2');

    Storage::disk('public')->put('a/photo.png', 'hello');
    Storage::disk('work_orders_private')->put('b/evidence.png', 'world');

    $this->artisan('storage:migrate-to-cloud')->assertSuccessful();

    expect(Storage::disk('r2')->get('a/photo.png'))->toBe('hello')
        ->and(Storage::disk('r2')->get('b/evidence.png'))->toBe('world');

    // Local copies are preserved (copy, not move).
    expect(Storage::disk('public')->exists('a/photo.png'))->toBeTrue();
});

it('does not write anything to the cloud disk on a dry run', function () {
    config(['filesystems.persistent_disk' => 'r2']);
    Storage::fake('public');
    Storage::fake('r2');
    Storage::disk('public')->put('a/photo.png', 'hello');

    $this->artisan('storage:migrate-to-cloud --scope=persistent --dry-run')->assertSuccessful();

    expect(Storage::disk('r2')->exists('a/photo.png'))->toBeFalse();
});

it('skips files already present with a matching checksum', function () {
    config(['filesystems.persistent_disk' => 'r2']);
    Storage::fake('public');
    Storage::fake('r2');
    Storage::disk('public')->put('a/photo.png', 'hello');
    Storage::disk('r2')->put('a/photo.png', 'hello'); // already migrated

    $this->artisan('storage:migrate-to-cloud --scope=persistent')
        ->expectsOutputToContain('Skipped (already present): 1')
        ->assertSuccessful();
});

it('refuses to migrate when the cloud disk is not configured', function () {
    // Defaults: persistent_disk === 'public' === source, so there is nothing to do.
    Storage::fake('public');
    Storage::disk('public')->put('a/photo.png', 'hello');

    $this->artisan('storage:migrate-to-cloud --scope=persistent')->assertSuccessful();
});
