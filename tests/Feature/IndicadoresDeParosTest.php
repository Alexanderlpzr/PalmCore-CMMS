<?php

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\StoppageReason;
use App\Domain\Assets\Services\DowntimeService;
use App\Filament\Pages\IndicadoresDeParos;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('agrupa las horas de paro por sección y por Tipo II', function (): void {
    $service = app(DowntimeService::class);

    // Falla mecánica en Extracción (1 h) y atascamiento en Esterilización (2 h), adyacentes.
    $service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'section' => PlantSection::Extraccion->value,
        'stoppage_reason' => StoppageReason::FallaMecanica->value,
        'started_at' => now()->subHours(3),
        'ended_at' => now()->subHours(2),
    ], $this->user);
    $service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'section' => PlantSection::Esterilizacion->value,
        'stoppage_reason' => StoppageReason::Atascamiento->value,
        'started_at' => now()->subHours(2),
        'ended_at' => now(),
    ], $this->user);

    $analytics = app(AnalyticsService::class);
    $bySection = collect($analytics->downtimeBySection($this->tenant->id, now()->subMonth(), now()))
        ->keyBy('label');
    $byReason = collect($analytics->downtimeByReason($this->tenant->id, now()->subMonth(), now()))
        ->keyBy('label');

    expect($bySection->get('Extracción')?->value)->toBe(1.0)
        ->and($bySection->get('Esterilización')?->value)->toBe(2.0)
        ->and($byReason->get('Falla mecánica')?->value)->toBe(1.0)
        ->and($byReason->get('Atascamiento')?->value)->toBe(2.0);
});

it('la página de indicadores de paros carga', function (): void {
    Livewire::test(IndicadoresDeParos::class)->assertOk();
});

it('el dashboard consolidado carga', function (): void {
    Livewire::test(App\Filament\Pages\Dashboard::class)->assertOk();
});
