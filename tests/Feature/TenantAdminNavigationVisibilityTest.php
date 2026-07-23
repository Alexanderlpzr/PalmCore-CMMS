<?php

use App\Filament\Pages\ApiTokens;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\CarouselSlides\CarouselSlideResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\WebhookSubscriptionResource;
use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use App\Filament\Resources\Plants\PlantResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * El menú del admin de tenant esconde Inventario, Gestión de Activos (menos
 * Equipos), Plantas, Webhooks y API Tokens — pero el superadministrador de
 * plataforma sí los ve. La distinción es is_super_admin, no un permiso: el
 * tenant admin tiene otro rol.
 */
$hidden = [
    WarehouseResource::class,
    PlantResource::class,
    WebhookSubscriptionResource::class,
    ApiTokens::class,
    CarouselSlideResource::class,
    AnnouncementResource::class,
];

it('esconde la navegación a un usuario de tenant (no superadmin)', function (string $class) {
    $this->actingAs(User::factory()->create(['is_super_admin' => false, 'is_active' => true]));

    expect($class::shouldRegisterNavigation())->toBeFalse();
})->with($hidden);

it('muestra la navegación al superadministrador de plataforma', function (string $class) {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));

    expect($class::shouldRegisterNavigation())->toBeTrue();
})->with($hidden);

it('esconde la navegación cuando no hay nadie autenticado', function (string $class) {
    expect($class::shouldRegisterNavigation())->toBeFalse();
})->with($hidden);

// ── Equipos: distinto — lo ve quien tenga el permiso, no solo el superadmin ──

it('muestra Equipos a un usuario de tenant con permiso de ver equipos', function () {
    $this->seed(PermissionSeeder::class);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_super_admin' => false, 'is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->givePermissionTo('equipment.view');

    $this->actingAs($user);

    expect(EquipmentResource::shouldRegisterNavigation())->toBeTrue();
});

it('esconde Equipos a un usuario de tenant sin ese permiso', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => false, 'is_active' => true]));

    expect(EquipmentResource::shouldRegisterNavigation())->toBeFalse();
});
