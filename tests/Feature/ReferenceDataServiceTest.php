<?php

use App\Domain\Assets\Services\ReferenceDataService;
use App\Models\Area;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('excludes soft-deleted areas from areas()', function () {
    $tenant = Tenant::factory()->create();
    $plant = Plant::factory()->for($tenant)->create();
    $active = Area::factory()->forPlant($plant)->create(['name' => 'Active Area']);
    $deleted = Area::factory()->forPlant($plant)->create(['name' => 'Deleted Area']);
    $deleted->delete();

    $result = ReferenceDataService::areas($plant->id);

    expect($result)->toHaveKey($active->id)
        ->and($result)->not->toHaveKey($deleted->id);
});

it('excludes soft-deleted areas from allAreas()', function () {
    $tenant = Tenant::factory()->create();
    $plant = Plant::factory()->for($tenant)->create();
    $active = Area::factory()->forPlant($plant)->create(['name' => 'Active Area']);
    $deleted = Area::factory()->forPlant($plant)->create(['name' => 'Deleted Area']);
    $deleted->delete();

    $result = ReferenceDataService::allAreas($tenant->id);

    expect($result)->toHaveKey($active->id)
        ->and($result)->not->toHaveKey($deleted->id);
});

it('excludes soft-deleted plants from plants()', function () {
    $tenant = Tenant::factory()->create();
    $active = Plant::factory()->for($tenant)->create(['name' => 'Active Plant']);
    $deleted = Plant::factory()->for($tenant)->create(['name' => 'Deleted Plant']);
    $deleted->delete();

    $result = ReferenceDataService::plants($tenant->id);

    expect($result)->toHaveKey($active->id)
        ->and($result)->not->toHaveKey($deleted->id);
});

it('area observer clears cache on delete so fresh query excludes soft-deleted area', function () {
    $tenant = Tenant::factory()->create();
    $plant = Plant::factory()->for($tenant)->create();
    $area = Area::factory()->forPlant($plant)->create(['name' => 'Soon Deleted']);

    // Warm the cache
    ReferenceDataService::areas($plant->id);

    // Deleting triggers AreaObserver::deleted() which calls forgetAreas()
    $area->delete();

    $result = ReferenceDataService::areas($plant->id);

    expect($result)->not->toHaveKey($area->id);
});
