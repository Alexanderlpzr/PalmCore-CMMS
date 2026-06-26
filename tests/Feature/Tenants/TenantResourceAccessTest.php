<?php

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\User;

it('hides TenantResource from navigation for non-super-admin', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    $this->actingAs($user);

    expect(TenantResource::shouldRegisterNavigation())->toBeFalse();
});

it('shows TenantResource in navigation for super admin', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($user);

    expect(TenantResource::shouldRegisterNavigation())->toBeTrue();
});

it('blocks viewAny for non-super-admin', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    $this->actingAs($user);

    expect(TenantResource::canViewAny())->toBeFalse();
});

it('allows viewAny for super admin', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($user);

    expect(TenantResource::canViewAny())->toBeTrue();
});
