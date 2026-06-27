<?php

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\User;

it('allows viewAny for super admin', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($user);

    expect(TenantResource::canViewAny())->toBeTrue();
});
