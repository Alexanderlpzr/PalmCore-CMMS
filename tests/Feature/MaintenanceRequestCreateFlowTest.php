<?php

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\CreateMaintenanceRequest;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->technician = User::factory()->create(['is_active' => true]);
    $this->technician->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->technician->assignRole('tecnico');

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->technician);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('skips the manual submit/take-for-review clicks and lands directly on under_review', function () {
    Livewire::test(CreateMaintenanceRequest::class)
        ->fillForm([
            'equipment_id' => $this->equipment->id,
            'request_type' => 'corrective',
            'priority' => 'p3_medium',
            'title' => 'Ruido extraño en bomba',
            'description' => 'Se escucha un ruido metálico intermitente.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = MaintenanceRequest::where('title', 'Ruido extraño en bomba')->firstOrFail();

    expect($request->status)->toBe(MaintenanceRequestStatus::UnderReview);
});
