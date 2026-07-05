<?php

use App\Domain\Reports\Services\ReportBrandingService;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

it('uses the tenant\'s own logo when one is uploaded', function () {
    Storage::fake(persistent_disk());
    Storage::disk(persistent_disk())->put('logos/acme.png', 'fake-png-bytes');

    $tenant = Tenant::factory()->create(['logo_path' => 'logos/acme.png']);

    $logo = app(ReportBrandingService::class)->logoBase64($tenant);

    expect($logo)->toStartWith('data:');
});

it('falls back to the Fronda CMMS logo when the tenant has none', function () {
    $tenant = Tenant::factory()->create(['logo_path' => null]);

    $logo = app(ReportBrandingService::class)->logoBase64($tenant);

    expect($logo)->not->toBeNull()
        ->and($logo)->toStartWith('data:image/png;base64,');
});

it('falls back to the Fronda CMMS logo when there is no tenant at all', function () {
    $logo = app(ReportBrandingService::class)->logoBase64(null);

    expect($logo)->not->toBeNull()
        ->and($logo)->toStartWith('data:image/png;base64,');
});
