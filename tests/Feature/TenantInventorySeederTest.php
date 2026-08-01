<?php

use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantInventorySeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();

    app(ProvisionTenantBaseStructure::class)->handle($this->tenant);
});

function inventoryEquipment(Tenant $tenant, string $code): ?Equipment
{
    return Equipment::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('code', $code)
        ->first();
}

it('gives every equipment a unique code in the A##XXX.##.## format', function () {
    $codes = Equipment::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->pluck('code');

    expect($codes)->toHaveCount(99)
        ->and($codes->unique())->toHaveCount(99)
        ->and($codes->filter(fn (string $code) => preg_match('/^A\d{2}[A-Z]{3}\.\d{2}\.\d{2}$/', $code) !== 1))
        ->toBeEmpty();
});

it('renumbers the nineteen boiler auxiliaries that shared A10SPG.13.02', function () {
    // El inventario original repetía el mismo código en los diecinueve; se
    // renumeran hacia abajo desde el primero, que conserva el suyo.
    $boiler = Equipment::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('code', 'like', 'A10SPG.13.%')
        ->orderBy('code')
        ->pluck('name', 'code');

    expect($boiler)->toHaveCount(19)
        ->and($boiler->keys()->first())->toBe('A10SPG.13.02')
        ->and($boiler->keys()->last())->toBe('A10SPG.13.20')
        ->and($boiler['A10SPG.13.02'])->toBe('Caldera Inducido #1')
        ->and($boiler['A10SPG.13.20'])->toBe('Caldera Ciclón A3');
});

it('splits the two clarification equipments that shared A06CLA.34.03', function () {
    expect(inventoryEquipment($this->tenant, 'A06CLA.34.03')->name)
        ->toBe('Bomba de Aceite Recuperado Centrífuga Alfa Laval')
        ->and(inventoryEquipment($this->tenant, 'A06CLA.34.04')->name)
        ->toBe('Centrífuga Alfa Laval');
});

it('repairs the codes that broke the station pattern', function (string $broken, string $fixed, string $name) {
    expect(inventoryEquipment($this->tenant, $broken))->toBeNull()
        ->and(inventoryEquipment($this->tenant, $fixed)?->name)->toBe($name);
})->with([
    // STR es la estación 02, no la 01 — el código original era imposible.
    ['A01STR.03.02_1', 'A02STR.03.02', 'Unidad Hidráulica Llenado Esterilizador'],
    ['A01STR.03.02_2', 'A02STR.03.03', 'Unidad Hidráulica Descarga Esterilizador'],
    // Le faltaba la «A» inicial; el .01.02 vecino confirma que el hueco es suyo.
    ['04EBT.01.01', 'A04EBT.01.01', 'Desfrutador Sin Eje'],
    ['A05EXT.05.01-A', 'A05EXT.05.02', 'Unidad Hidráulica Prensa P15'],
    // Chocaba con el Elevador de Almendras, que ya ocupaba el .20.01.
    ['A08KRS.20.01-A', 'A08KRS.20.02', 'Parrilla Silo de Almendras'],
    ['A08KRS-21.01-B', 'A08KRS.21.01', 'Ventilador del Silo de Almendras'],
    ['A10SPG.17.01-A', 'A10SPG.17.01', 'Esclusa para Ceniza #1'],
    ['A10SPG.17.01-D', 'A10SPG.17.04', 'Esclusa para Ceniza #4'],
]);

it('files each equipment under the area its section names, not its code prefix', function () {
    // Palmistería opera equipos DEP y KRS; Desfibrado, KRS y SPG. El prefijo es
    // la estación de proceso y no tiene por qué coincidir con el área.
    expect(inventoryEquipment($this->tenant, 'A08KRS.01.01')->area->code)->toBe('PAL-01')
        ->and(inventoryEquipment($this->tenant, 'A10SPG.01.02')->area->code)->toBe('DFB-01')
        ->and(inventoryEquipment($this->tenant, 'A08KRS.22.01')->area->code)->toBe('DFB-01')
        ->and(inventoryEquipment($this->tenant, 'A19CMP.01.01')->area->code)->toBe('COG-01');
});

it('applies the spelling fixes carried over from the field inventory', function () {
    $names = Equipment::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('name');
    $components = EquipmentComponent::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->distinct()
        ->pluck('name');

    expect($names)->not->toContain('Redler #1 Fruta de lasTolvas')
        ->and($names)->toContain('Redler #1 Fruta de las Tolvas')
        ->and($components)->not->toContain('Sistema motirz')
        ->and($components)->toContain('Sistema motriz')
        ->and($components)->not->toContain('Sistema de dosificacíon')
        ->and($components)->toContain('Sistema de dosificación')
        ->and($components)->not->toContain('Bases y soportes de siuspensión')
        ->and($components)->toContain('Bases y soportes de suspensión')
        // «transacción» no existe en una banda transportadora: es traslación.
        ->and($components)->not->toContain('Sistema de transacción y rodamiento')
        ->and($components)->toContain('Sistema de traslación y rodamiento');
});

it('moves an embedded manufacturer reference out of the name and into the model', function () {
    $pump = inventoryEquipment($this->tenant, 'A06CLA.23.02');

    expect($pump->name)->toBe('Bomba de Vacío SIHI Halberg')
        ->and($pump->model)->toBe('SIHI LPHX 55312 AB');
});

it('gives the five equipments the inventory left undetailed a flagged component list', function () {
    // El archivo repetía el nombre del equipo como único «componente». Se
    // completan con la plantilla de su tipo y quedan marcados como no validados.
    $cyclone = inventoryEquipment($this->tenant, 'A10SPG.13.20');

    expect($cyclone->notes)->toBe(TenantInventorySeeder::UNVERIFIED_COMPONENTS_NOTE)
        ->and($cyclone->components()->pluck('name')->all())->not->toContain($cyclone->name)
        ->and($cyclone->components()->count())->toBe(5);

    expect(inventoryEquipment($this->tenant, 'A10SPG.13.17')->components()->pluck('name')->all())
        ->toEqualCanonicalizing(['Unidad de potencia', 'Elementos de control y regulación', 'Actuadores', 'Transmisión y sellado']);
});

it('categorises boiler auxiliaries by what they are, not by what they hang off', function () {
    expect(inventoryEquipment($this->tenant, 'A10SPG.13.07')->category->name)->toBe('Sinfín')
        ->and(inventoryEquipment($this->tenant, 'A10SPG.13.09')->category->name)->toBe('Bomba')
        ->and(inventoryEquipment($this->tenant, 'A10SPG.13.15')->category->name)->toBe('Redler')
        ->and(inventoryEquipment($this->tenant, 'A10SPG.13.02')->category->name)->toBe('Ventilador');
});

it('is additive — re-running never duplicates equipment or components', function () {
    $plant = $this->tenant->plants()->withoutGlobalScopes()->first();

    (new TenantInventorySeeder)->run($this->tenant, $plant);

    expect(Equipment::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(99)
        ->and(EquipmentComponent::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(461);
});

it('leaves a manually edited equipment untouched when re-run', function () {
    $plant = $this->tenant->plants()->withoutGlobalScopes()->first();

    inventoryEquipment($this->tenant, 'A05EXT.04.01')->update(['name' => 'Digestor Vertical (repotenciado 2026)']);

    (new TenantInventorySeeder)->run($this->tenant, $plant);

    expect(inventoryEquipment($this->tenant, 'A05EXT.04.01')->name)
        ->toBe('Digestor Vertical (repotenciado 2026)');
});
