<?php

use App\Actions\Tenants\CreateTenantAdmin;
use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\CarouselSlides\CarouselSlideResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(ProvisionTenantBaseStructure::class)->handle($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lets an administrador-general see and manage Carrusel and Contenido', function () {
    $admin = app(CreateTenantAdmin::class)->handle(
        $this->tenant, 'Ada', 'ada@acme.test', 'Admin123'
    );

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin->fresh());

    expect(AnnouncementResource::canViewAny())->toBeTrue()
        ->and(AnnouncementResource::canCreate())->toBeTrue()
        ->and(CarouselSlideResource::canViewAny())->toBeTrue()
        ->and(CarouselSlideResource::canCreate())->toBeTrue();
});

it('hides Carrusel and Contenido from a non-admin role', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($this->tenant->id);
    $user->assignRole('gerencia'); // read-only executive — no content permissions
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user->fresh());

    expect(AnnouncementResource::canViewAny())->toBeFalse()
        ->and(CarouselSlideResource::canViewAny())->toBeFalse();
});

it('lets a super admin see and manage Carrusel and Contenido', function () {
    $super = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
    $this->actingAs($super);

    expect(AnnouncementResource::canViewAny())->toBeTrue()
        ->and(CarouselSlideResource::canViewAny())->toBeTrue();
});
