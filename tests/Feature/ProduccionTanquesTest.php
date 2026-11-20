<?php

use App\Domain\Commercial\Services\OilTankService;
use App\Domain\Commercial\Services\ProductionWorkbookImporter;
use App\Filament\Pages\CuadroCruce;
use App\Filament\Pages\TanquesAceite;
use App\Filament\Resources\Commercial\KernelInventoryCount\Pages\ManageKernelInventoryCounts;
use App\Filament\Resources\Commercial\KernelTollLoad\KernelTollLoadResource;
use App\Filament\Resources\Commercial\KernelTollLoad\Pages\ManageKernelTollLoads;
use App\Filament\Resources\Commercial\OilTank\OilTankResource;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Customer;
use App\Models\KernelInventoryCount;
use App\Models\KernelTollLoad;
use App\Models\OilTank;
use App\Models\OilTankReading;
use App\Models\SalesDispatch;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\PermissionRegistrar;

/*
 * Fase 3 del módulo comercial: los tanques de aceite —la producción sale de los niveles y
 * los despachos—, la maquila de la almendra y el inventario de palmiste y torta.
 *
 * Hoy es el 18 de noviembre de 2026.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00'));

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $persona = function (string $rol): User {
        $user = User::factory()->create(['is_active' => true]);
        $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $user->assignRole($rol);

        return $user;
    };

    $this->directora = $persona('direccion-administrativa');
    $this->proceso = $persona('proceso');
    $this->mantenimiento = $persona('mantenimiento');

    $this->tanque1 = OilTank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tanque 1', 'sort_order' => 1]);
    $this->tanque2 = OilTank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tanque 2', 'sort_order' => 2]);
    $this->cliente = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Manuelita']);
    $this->tanques = app(OilTankService::class);
});

function enLaPlanta(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant(test()->tenant);
}

function medir(string $fecha, array $niveles): void
{
    test()->tanques->record(test()->tenant->id, CarbonImmutable::parse($fecha), [
        test()->tanque1->id => $niveles[0],
        test()->tanque2->id => $niveles[1] ?? [],
    ], null);
}

it('works out the production from the tank levels and the loads, and what is left to sell', function (): void {
    medir('2026-11-10', [['level' => '300', 'acidity' => '4'], ['level' => '200', 'acidity' => '5']]);
    SalesDispatch::factory()->create(['customer_id' => $this->cliente->id, 'loaded_on' => '2026-11-11', 'departure_weight_tons' => 34]);
    medir('2026-11-11', [['level' => '320', 'acidity' => '4'], ['level' => '210,5', 'acidity' => '5']]);
    // Dos días sin medir: la producción del 13 es la de los dos días.
    SalesDispatch::factory()->create(['customer_id' => $this->cliente->id, 'loaded_on' => '2026-11-12', 'departure_weight_tons' => 30]);
    medir('2026-11-13', [['level' => '330', 'acidity' => '6'], ['level' => '220', 'acidity' => '4']]);
    // Un día con un solo tanque medido: no se compara.
    medir('2026-11-14', [['level' => '340']]);
    // Despachado después de la última medición.
    SalesDispatch::factory()->create(['customer_id' => $this->cliente->id, 'loaded_on' => '2026-11-15', 'departure_weight_tons' => 33.5]);

    $dias = collect($this->tanques->days($this->tenant->id, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')))
        ->keyBy(fn (array $dia): string => $dia['date']->toDateString());

    expect($dias['2026-11-10']['production'])->toBeNull()
        // Acidez de la mezcla: (300 × 4 + 200 × 5) / 500.
        ->and($dias['2026-11-10']['acidity'])->toBe(4.4)
        // 530,5 − 500 + 34.
        ->and($dias['2026-11-11']['production'])->toBe(64.5)
        ->and($dias['2026-11-11']['dispatched'])->toBe(34.0)
        // 550 − 530,5 + 30, en dos días.
        ->and($dias['2026-11-13']['production'])->toBe(49.5)
        ->and($dias['2026-11-13']['days'])->toBe(2)
        ->and($dias['2026-11-14']['production'])->toBeNull()
        ->and($this->tanques->monthlyProduction($this->tenant->id, 2026)[11])->toBe(114.0);

    $hoy = $this->tanques->latest($this->tenant->id);
    expect($hoy['date']->toDateString())->toBe('2026-11-14')
        ->and($hoy['total'])->toBe(340.0)
        ->and($hoy['available'])->toBe(306.5)
        // Medido hace cuatro días: el disponible ya no dice cuánto hay.
        ->and($hoy['stale'])->toBeTrue();

    // Sin nivel, la medición del tanque se borra.
    medir('2026-11-14', [['level' => '']]);
    expect(OilTankReading::query()->whereDate('reading_date', '2026-11-14')->exists())->toBeFalse()
        ->and(fn () => medir('2026-11-30', [['level' => '10']]))->toThrow(DomainException::class);
});

it('lets the director measure the tanks and keeps process out of production', function (): void {
    // Proceso no ve producción (2026-11-19): ni los tanques, ni ventas, ni el cuadro, ni la maquila.
    enLaPlanta($this->proceso);

    expect(TanquesAceite::canAccess())->toBeFalse()
        ->and($this->proceso->can('record', OilTank::class))->toBeFalse()
        ->and(SalesDispatchResource::canViewAny())->toBeFalse()
        ->and(CuadroCruce::canAccess())->toBeFalse()
        ->and(KernelTollLoadResource::canViewAny())->toBeFalse()
        ->and(OilTankResource::shouldRegisterNavigation())->toBeFalse();

    $this->actingAs($this->proceso->fresh())->get(TanquesAceite::getUrl(tenant: $this->tenant))->assertForbidden();

    enLaPlanta($this->directora);

    Livewire::test(TanquesAceite::class)
        ->assertOk()
        ->assertSee('Medición del día')
        ->set('date', '2026-11-18')
        ->set("levels.{$this->tanque1->id}.level", '412,30')
        ->set("levels.{$this->tanque1->id}.acidity", '5,4')
        ->set("levels.{$this->tanque2->id}.level", '180')
        ->call('guardar')
        ->assertSee('412,30')
        // La acidez por encima del límite se marca.
        ->assertSee('5,40 %');

    expect((float) OilTankReading::query()->where('oil_tank_id', $this->tanque1->id)->sole()->level_tons)->toBe(412.3);

    enLaPlanta($this->mantenimiento);
    expect(TanquesAceite::canAccess())->toBeFalse();
});

it('shows the oil available on the cross table', function (): void {
    medir('2026-11-17', [['level' => '400', 'acidity' => '3'], ['level' => '100', 'acidity' => '3']]);
    enLaPlanta($this->directora);

    Livewire::test(CuadroCruce::class)
        ->assertSee('Aceite disponible hoy')
        ->assertSee('500,00 tn');
});

it('accounts for each toll load: shrinkage, yields, cake income and balance', function (): void {
    $cargue = KernelTollLoad::factory()->create([
        'tenant_id' => $this->tenant->id,
        'arrival_weight_tons' => 35.14,
        'processed_kg' => 36542,
        'oil_kg' => 15713,
        'cake_kg' => 18636,
        'toll_amount' => 9_902_882,
        'freight_amount' => 4_933_170,
        'cake_price_per_ton' => 700_000,
    ]);

    // Los del primer cargue de 2026 en el Excel.
    expect($cargue->shrinkageKg())->toBe(-1402)
        ->and($cargue->cakeIncome())->toBe(13_045_200.0)
        ->and($cargue->balance())->toBe(-1_790_852.0)
        ->and(round($cargue->oilYield(), 3))->toBe(0.43)
        // Sin precio de maquila todavía no hay saldo: la torta sola parecería ganancia.
        ->and(KernelTollLoad::factory()->make(['toll_amount' => null])->balance())->toBeNull();

    KernelInventoryCount::factory()->create(['tenant_id' => $this->tenant->id, 'counted_on' => '2026-07-24', 'pko_tons' => 33.844, 'cake_tons' => 41.852]);
    enLaPlanta($this->directora);

    Livewire::test(ManageKernelTollLoads::class)->assertOk()->assertCanSeeTableRecords([$cargue])->assertSee('Torta de maquila en 2026');
    Livewire::test(ManageKernelInventoryCounts::class)->assertOk()->assertSee('Palmiste en bodega')->assertSee('33,84 tn');
});

it('imports the tanks, the toll loads and the inventory from the Excel workbook', function (): void {
    $ruta = libroDeLaPlanta();

    $summary = app(ProductionWorkbookImporter::class)->import($ruta, $this->tenant->id, $this->directora);

    expect($summary)->toBe(['tank_days' => 2, 'toll_loads' => 2, 'inventory_counts' => 1]);

    $medicion = OilTankReading::query()->where('oil_tank_id', $this->tanque2->id)->whereDate('reading_date', '2026-07-28')->sole();
    expect((float) $medicion->level_tons)->toBe(150.0)
        ->and((float) $medicion->acidity_percent)->toBe(3.2);

    $cargue = KernelTollLoad::query()->where('plate', 'TFK742')->sole();
    expect($cargue->processed_kg)->toBe(36542)
        ->and($cargue->balance())->toBe(-1_790_852.0)
        ->and((float) KernelInventoryCount::query()->sole()->cake_tons)->toBe(41.85);

    // Otra vez: nada se repite.
    expect(app(ProductionWorkbookImporter::class)->import($ruta, $this->tenant->id, $this->directora))
        ->toBe(['tank_days' => 0, 'toll_loads' => 0, 'inventory_counts' => 0]);
});

/** Las tres hojas de la planta, con la forma del libro de la Directora. */
function libroDeLaPlanta(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'planta').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $fecha = fn (string $dia): DateTimeImmutable => new DateTimeImmutable($dia);

    $writer->getCurrentSheet()->setName('Disponibilidad Aceite');
    $writer->addRow(Row::fromValues([' |']));
    $writer->addRow(Row::fromValues(['Actualización diaria.']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Inventario inicial disponible al 1-ene-2026 (tn):', '', '', 0]));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Fecha', 'Producción real del día (tn)', 'Despachado', 'Disponible', 'Inventario', 'Tanque 1 (tn)', 'Acidez T1', 'Tanque 2 (tn)', 'Acidez T2']));
    $writer->addRow(Row::fromValues([$fecha('2026-07-27'), 281, 0, 281, 281, 0, 0, 0, 0]));
    $writer->addRow(Row::fromValues([$fecha('2026-07-28'), 33, 0, 33, 314, 420.5, 4.1, 150, 3.2]));
    $writer->addRow(Row::fromValues([$fecha('2026-07-29'), 29, 0, 29, 343, 430, 4.0, 0, 0]));
    $writer->addRow(Row::fromValues(['Cómo usar esta hoja:']));

    $writer->addNewSheetAndMakeItCurrent()->setName('Almendra y PKO (Maquila)');
    $writer->addRow(Row::fromValues(['PAJUIL — ALMENDRA: MAQUILA (TORTA) Y VENTA DE PALMISTE (PKO) 2026']));
    $writer->addRow(Row::fromValues(['Igual estructura que el archivo… VENTA PALMISTE (venta comercial real de PKO).']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['MAQUILA — PRODUCCIÓN DE TORTA (Y ACEITE ESTIMADO) 2026']));
    $writer->addRow(Row::fromValues(['Fecha Cargue', 'Placa', 'Estado Descargue', 'Fecha Descargue', 'Remisión', 'Peso Salida (tn)', 'Peso Llegada (tn)', 'Merma (kg)', 'Entra a Proceso (kg)', 'ACEITE estimado (kg)', 'TORTA (kg)', 'Acum. Aceite (kg)', 'Precio Maquila ($)', 'Flete ($)', 'Precio Torta ($/tn)', 'Ingreso por Torta ($)', 'Saldo ($)']));
    $writer->addRow(Row::fromValues([$fecha('2026-01-03'), 'TFK742', '', $fecha('2026-01-04'), 8841, 35.47, 35.14, -1402, 36542, 15713, 18636, 1693163.48, 9902882, 4933170, 700000, 13045200, -1790852]));
    $writer->addRow(Row::fromValues([$fecha('2026-01-16'), 'UFK172', '', $fecha('2026-01-19'), 8945, 34.48, 34.27, -134, 34404, 14794, 17546, 1707957.48, 9323484, 4644540, 700000, 12282200, -1685824]));
    $writer->addRow(Row::fromValues(['TOTAL MAQUILA 2026', '', '', '', '', 69.95]));
    $writer->addRow(Row::fromValues(['VENTA PALMISTE (PKO) 2026 — VENTAS COMERCIALES REALES']));
    $writer->addRow(Row::fromValues(['Fecha Cargue', 'Placa']));
    $writer->addRow(Row::fromValues([$fecha('2026-01-13'), 'LGL611', 'Descargado', $fecha('2026-01-15'), 1542, '134026-1']));

    $writer->addNewSheetAndMakeItCurrent()->setName('Inventario Palmiste PKO');
    $writer->addRow(Row::fromValues(['PAJUIL — INVENTARIO DE PALMISTE (PKO) Y TORTA']));
    $writer->addRow(Row::fromValues(['Digite en las columnas amarillas…']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Fecha', 'PKO / Palmiste en inventario (tn)', 'Torta de Palmiste en inventario (tn)', 'Total (tn)']));
    $writer->addRow(Row::fromValues([$fecha('2026-07-23'), '', '', 0]));
    $writer->addRow(Row::fromValues([$fecha('2026-07-24'), 33.844, 41.852, 75.696]));

    $writer->close();

    return $ruta;
}
