<?php

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * «Volver» en los listados regresa a la pantalla anterior con JavaScript. La dirección de
 * respaldo iba entre comillas dobles, que llegaban escapadas (\") al x-on:click: Alpine no
 * podía leer la expresión y el botón no hacía nada (reportado el 2026-11-05).
 */

it('writes the back button expression so Alpine can read it', function (): void {
    $this->seed(PermissionSeeder::class);
    $tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($tenant);
    setPermissionsTeamId($tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($tenant);

    $rrhh = User::factory()->create(['is_active' => true]);
    $rrhh->tenants()->attach($tenant->id, ['joined_at' => now()]);
    $rrhh->assignRole('talento-humano');
    $this->actingAs($rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($tenant);

    $html = Livewire::test(ListEmployees::class)->assertOk()->html();

    preg_match('/window\.history\.back\(\) : \(window\.location\.href = (.*?)\)/', html_entity_decode($html), $match);

    expect($match)->not->toBeEmpty()
        ->and($match[1])->toStartWith("'")
        ->and($match[1])->toEndWith("'")
        ->and($match[1])->not->toContain('\\');
});
