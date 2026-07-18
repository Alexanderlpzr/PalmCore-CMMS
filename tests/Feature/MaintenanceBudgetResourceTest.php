<?php

use App\Filament\Resources\MaintenanceBudgets\Pages\CreateMaintenanceBudget;
use App\Filament\Resources\MaintenanceBudgets\Pages\ListMaintenanceBudgets;
use App\Models\MaintenanceBudget;
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

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('creates a budget filling tenant and creator from context', function (): void {
    Livewire::test(CreateMaintenanceBudget::class)
        ->fillForm([
            'plant_id' => $this->plant->id,
            'year' => 2026,
            'month' => 7,
            'amount' => 50_000_000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $budget = MaintenanceBudget::withoutGlobalScopes()->sole();

    expect($budget->tenant_id)->toBe($this->tenant->id)
        ->and($budget->created_by)->toBe($this->user->id)
        ->and($budget->amount)->toBe(50000000.0);
});

it('correcting the budget of a month updates it instead of duplicating', function (): void {
    MaintenanceBudget::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026,
        'month' => 7,
        'amount' => 10_000_000,
    ]);

    Livewire::test(CreateMaintenanceBudget::class)
        ->fillForm([
            'plant_id' => $this->plant->id,
            'year' => 2026,
            'month' => 7,
            'amount' => 80_000_000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $budgets = MaintenanceBudget::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)
        ->where('year', 2026)
        ->where('month', 7)
        ->get();

    expect($budgets)->toHaveCount(1)
        ->and($budgets->first()->amount)->toBe(80000000.0);
});

it('lists budgets for the current tenant', function (): void {
    MaintenanceBudget::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026,
        'month' => 6,
        'amount' => 42_000_000,
    ]);

    Livewire::test(ListMaintenanceBudgets::class)
        ->assertOk()
        ->assertSee($this->plant->name);
});
