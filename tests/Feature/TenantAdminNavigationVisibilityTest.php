<?php

use App\Filament\Pages\ApiTokens;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\WebhookSubscriptionResource;
use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use App\Filament\Resources\Plants\PlantResource;
use App\Models\User;

/**
 * El menú del admin de tenant esconde Inventario, Gestión de Activos, Plantas,
 * Webhooks y API Tokens — pero el superadministrador de plataforma sí los ve.
 * La distinción es is_super_admin, no un permiso: el tenant admin tiene otro rol.
 */
$hidden = [
    EquipmentResource::class,
    WarehouseResource::class,
    PlantResource::class,
    WebhookSubscriptionResource::class,
    ApiTokens::class,
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
