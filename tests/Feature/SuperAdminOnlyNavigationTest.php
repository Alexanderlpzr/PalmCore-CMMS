<?php

use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Filament\Pages\DataAudit;
use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Automation\AutomationRule\AutomationRuleResource;
use App\Filament\Resources\Contractors\ContractorResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\EquipmentCategories\EquipmentCategoryResource;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Plants\PlantResource;
use App\Filament\Resources\ProductionCalendar\ProductionCalendarResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Spatie\Permission\PermissionRegistrar;

/** Pantallas que administra el proveedor y la planta no debe ver. */
const RESTRINGIDAS = [
    UserResource::class,
    RoleResource::class,
    PermissionResource::class,
    AutomationRuleResource::class,
    DataAudit::class,
];

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(ProvisionTenantBaseStructure::class)->handle($this->tenant);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    // isQuiet: el evento TenantSet exige un usuario autenticado, y aquí todavía
    // no lo hay — cada test elige el suyo (administrador del tenant o super).
    Filament::setTenant($this->tenant, isQuiet: true);
});

/** Administrador del tenant: tiene el rol completo de su empresa. */
function tenantAdmin(Tenant $tenant): User
{
    $user = User::factory()->create(['is_super_admin' => false, 'is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->assignRole('administrador-general');

    return $user;
}

// ── El bloqueo ───────────────────────────────────────────────────────────────

it('hides the vendor-only screens from the tenant administrator', function (string $screen): void {
    // El administrador general del tenant tiene TODOS los permisos de su
    // empresa, incluido users.view: si el bloqueo dependiera del permiso, esto
    // pasaría igual. Por eso se comprueba con el rol más alto de la planta.
    $this->actingAs(tenantAdmin($this->tenant));

    expect($screen::canAccess())->toBeFalse();
})->with(RESTRINGIDAS);

it('still lets the super admin in', function (string $screen): void {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));

    expect($screen::canAccess())->toBeTrue();
})->with(RESTRINGIDAS);

it('keeps the link out of the menu for the same reason it blocks the route', function (): void {
    // Un solo gancho para las dos cosas: Filament pregunta canAccess() antes de
    // registrar el enlace (HasNavigation) y antes de dejar entrar por la URL.
    // Ocultar solo el enlace sería seguridad de escaparate.
    //
    // Ojo: shouldRegisterNavigation() NO sirve para esto — es la propiedad que
    // dice si el recurso quiere salir en el menú, y sigue en true; la puerta
    // real es canAccess(), que es lo que se comprueba aquí.
    $this->actingAs(tenantAdmin($this->tenant));

    $items = collect(RESTRINGIDAS)->flatMap(fn (string $screen): array => $screen::canAccess()
        ? [$screen]
        : []);

    expect($items)->toBeEmpty();
});

// ── Lo que la planta sí necesita ─────────────────────────────────────────────

it('leaves the daily work reachable for the tenant', function (string $screen): void {
    // Si esto se rompiera, el ingeniero no podría cargar horas ni toneladas y
    // los tres indicadores dirían «Sin horas pagadas» para siempre.
    $this->actingAs(tenantAdmin($this->tenant));

    expect($screen::canAccess())->toBeTrue();
})->with([
    ProductionCalendarResource::class,
    EquipmentResource::class,
    PlantResource::class,
    AreaResource::class,
]);

// ── La fusión del grupo ──────────────────────────────────────────────────────

it('gathers the plant structure in the same group as Equipos', function (): void {
    $grupo = EquipmentResource::getNavigationGroup();

    expect($grupo)->toBe('Gestión de Activos')
        ->and(PlantResource::getNavigationGroup())->toBe($grupo)
        ->and(AreaResource::getNavigationGroup())->toBe($grupo)
        ->and(ProductionCalendarResource::getNavigationGroup())->toBe($grupo);
});

it('orders that group by the real hierarchy, with no ties', function (): void {
    $orden = [
        PlantResource::class => 1,
        AreaResource::class => 2,
        EquipmentResource::class => 3,
        ProductionCalendarResource::class => 4,
    ];

    foreach ($orden as $resource => $sort) {
        expect($resource::getNavigationSort())->toBe($sort, $resource);
    }

    // Un empate deja el orden del menú al azar entre dos recargas.
    $sorts = collect([
        EquipmentCategoryResource::class,
        ManufacturerResource::class,
        SupplierResource::class,
        ContractorResource::class,
        ...array_keys($orden),
    ])->map(fn (string $r): ?int => $r::getNavigationSort());

    expect($sorts->unique())->toHaveCount($sorts->count());
});
