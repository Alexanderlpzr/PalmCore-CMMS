<?php

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\Market;
use App\Domain\Commercial\Enums\PlanVersion;
use App\Domain\Commercial\Services\AnnualPlanService;
use App\Domain\Commercial\Services\CommercialPlanReport;
use App\Domain\Commercial\Services\PlanWorkbookImporter;
use App\Filament\Pages\CuadroCruce;
use App\Filament\Pages\FrutaRecibida;
use App\Filament\Pages\PlanAnual;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AnnualPlan;
use App\Models\AnnualPlanFruit;
use App\Models\Customer;
use App\Models\FruitReception;
use App\Models\FruitSupplier;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
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
 * Fase 2 del módulo comercial: el plan del año —fruta por proveedor, planta y ventas por
 * cliente— con su original congelado y su reproyección, la fruta recibida y el cuadro
 * cruce contra los despachos.
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
    $this->administrador = $persona('administrador-general');

    $this->soagrocol = FruitSupplier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Soagrocol', 'sort_order' => 1]);
    $this->guariapalma = FruitSupplier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Guariapalma', 'sort_order' => 2]);
    $this->manuelita = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Manuelita']);
    $this->acepalma = Customer::factory()->export()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acepalma']);
    $this->planes = app(AnnualPlanService::class);
});

function enElPanelComercial(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant(test()->tenant);
}

function planDe2026(): AnnualPlan
{
    $plan = test()->planes->createOriginal(test()->tenant->id, 2026, test()->directora);
    test()->planes->setFruit($plan, test()->soagrocol->id, 1, '2.846');
    test()->planes->setFruit($plan, test()->guariapalma->id, 1, '2956');
    test()->planes->setFruit($plan, test()->soagrocol->id, 2, 2681);
    test()->planes->setMonthParameter($plan, 1, 'tea_percent', '20,49');
    test()->planes->setMonthParameter($plan, 1, 'plant_capacity_tons', 7500);
    test()->planes->setMonthParameter($plan, 2, 'tea_percent', 19.61);
    test()->planes->setSale($plan, test()->manuelita->id, CommercialProduct::Cpo, 1, 450.53);
    test()->planes->setSale($plan, AnnualPlanService::UNASSIGNED, CommercialProduct::Cpo, 1, 260);
    test()->planes->setSale($plan, test()->acepalma->id, CommercialProduct::Pko, 1, 136);
    test()->planes->setOpeningOil($plan, 392);

    return $plan;
}

it('computes the oil, kernel and PKO of the plan and whether the oil is enough', function (): void {
    $plan = planDe2026();
    $meses = app(CommercialPlanReport::class)->plantMonths($plan);

    // Enero: 2.846 + 2.956 = 5.802 tn de fruta × 20,49 % = 1.188,83 tn de aceite.
    expect($meses[1]['fruit'])->toBe(5802.0)
        ->and($meses[1]['cpo'])->toBe(1188.83)
        ->and(round($meses[1]['utilization'], 4))->toBe(0.7736)
        // Almendra al 5 % y PKO al 43 % de la almendra, las tasas por defecto.
        ->and($meses[1]['kernel'])->toBe(290.1)
        ->and($meses[1]['pko'])->toBe(124.74)
        // Ventas de aceite: Manuelita y lo que está por concretar.
        ->and($meses[1]['sales_cpo'])->toBe(710.53)
        ->and($meses[1]['sales_pko'])->toBe(136.0)
        ->and($meses[1]['balance'])->toBe(478.3)
        // Arranca con el inventario inicial.
        ->and($meses[1]['cumulative'])->toBe(870.3)
        ->and($meses[2]['cpo'])->toBe(525.74)
        ->and($meses[2]['cumulative'])->toBe(1396.04);
});

it('freezes the approved original and opens the reprojection as a copy', function (): void {
    $plan = planDe2026();
    $reproyeccion = $this->planes->approve($plan, $this->directora);

    expect($plan->fresh()->approved_at)->not->toBeNull()
        ->and($plan->fresh()->isEditable())->toBeFalse()
        ->and($reproyeccion->version)->toBe(PlanVersion::Reproyeccion)
        ->and($reproyeccion->isEditable())->toBeTrue()
        ->and((float) $reproyeccion->opening_oil_tons)->toBe(392.0)
        ->and($reproyeccion->fruit()->count())->toBe(3)
        ->and($reproyeccion->sales()->count())->toBe(3)
        ->and($reproyeccion->months()->count())->toBe(12)
        ->and($this->planes->current($this->tenant->id, 2026)->id)->toBe($reproyeccion->id);

    // El original no se toca: los ajustes van en la reproyección.
    expect(fn () => $this->planes->setFruit($plan->fresh(), $this->soagrocol->id, 1, 3000))
        ->toThrow(DomainException::class, 'los ajustes van en la reproyección');

    $this->planes->setFruit($reproyeccion, $this->soagrocol->id, 1, 3000);
    $report = app(CommercialPlanReport::class);
    expect($report->fruitPlan($reproyeccion)[$this->soagrocol->id][1])->toBe(3000.0)
        ->and($report->fruitPlan($plan)[$this->soagrocol->id][1])->toBe(2846.0);

    // Vacía o en cero, la celda se borra; y no se aprueba dos veces.
    $this->planes->setFruit($reproyeccion, $this->soagrocol->id, 1, '');
    expect(AnnualPlanFruit::query()->where('annual_plan_id', $reproyeccion->id)->where('fruit_supplier_id', $this->soagrocol->id)->where('month', 1)->exists())->toBeFalse()
        ->and(fn () => $this->planes->approve($plan->fresh(), $this->directora))->toThrow(DomainException::class);
});

it('rejects negative tons and percentages out of range', function (): void {
    $plan = $this->planes->createOriginal($this->tenant->id, 2026, null);

    expect(fn () => $this->planes->setFruit($plan, $this->soagrocol->id, 10, -217))->toThrow(DomainException::class, 'negativas')
        ->and(fn () => $this->planes->setMonthParameter($plan, 1, 'tea_percent', 120))->toThrow(DomainException::class)
        ->and(fn () => $this->planes->setFruit($plan, $this->soagrocol->id, 1, 'mucha'))->toThrow(DomainException::class);
});

it('crosses the plan with the loads: dispatched, in transit, pending, billed and owed per customer', function (): void {
    $plan = planDe2026();
    $this->planes->setSale($plan, $this->manuelita->id, CommercialProduct::Cpo, 2, 165.58);

    SalesDispatch::factory()->paid()->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2026-01-09', 'arrival_weight_tons' => 34.2, 'invoice_amount' => 145_000_000]);
    SalesDispatch::factory()->invoiced(139_000_000)->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2026-02-10', 'arrival_weight_tons' => 32.81]);
    SalesDispatch::factory()->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2026-11-16', 'departure_weight_tons' => 34.5]);
    // Otro año: no cuenta.
    SalesDispatch::factory()->paid()->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2025-12-30']);

    enElPanelComercial($this->directora);
    $datos = Livewire::test(CuadroCruce::class)->instance()->data();
    $fila = $datos['products']['cpo']['rows'][$this->manuelita->id];

    expect($fila['projected'])->toBe(616.11)
        ->and($fila['dispatched'])->toBe(67.01)
        ->and($fila['in_transit'])->toBe(34.5)
        ->and($fila['pending'])->toBe(514.6)
        ->and($fila['dispatched_months'])->toBe([1 => 34.2, 2 => 32.81])
        ->and($fila['invoiced'])->toBe(284_000_000.0)
        ->and($fila['paid'])->toBe(145_000_000.0)
        ->and($fila['receivable'])->toBe(139_000_000.0)
        ->and(CuadroCruce::billingState($fila))->toBe('Pendiente de cobro')
        // «Por concretar» también se proyecta.
        ->and($datos['products']['cpo']['rows'][AnnualPlanService::UNASSIGNED]['projected'])->toBe(260.0);

    Livewire::test(CuadroCruce::class)
        ->assertOk()
        ->assertSee('Avance por cliente')
        ->assertSee('Manuelita')
        ->assertSee('Por concretar');
});

it('records the fruit received per supplier and month and checks it against the plant', function (): void {
    planDe2026();
    $plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductionCalendarDay::query()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $plant->id, 'calendar_date' => '2026-01-05', 'programmed_hours' => 20, 'processed_tons' => 2000]);
    ProductionCalendarDay::query()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $plant->id, 'calendar_date' => '2026-01-06', 'programmed_hours' => 20, 'processed_tons' => 1100.5]);

    enElPanelComercial($this->directora);

    Livewire::test(FrutaRecibida::class, ['year' => 2026])
        ->call('guardar', $this->soagrocol->id, 1, '2.326,41')
        ->call('guardar', $this->guariapalma->id, 1, '858,51')
        ->assertOk()
        ->assertSee('Soagrocol')
        ->assertSee('Fruta procesada en planta');

    $report = app(CommercialPlanReport::class);
    expect($report->fruitReal($this->tenant->id, 2026)[$this->soagrocol->id][1])->toBe(2326.41)
        ->and($report->plantProcessed($this->tenant->id, 2026)[1])->toBe(3100.5)
        ->and(FruitReception::query()->count())->toBe(2);

    // Corregir la celda la cambia; vaciarla la borra.
    Livewire::test(FrutaRecibida::class, ['year' => 2026])->call('guardar', $this->soagrocol->id, 1, '');
    expect(FruitReception::query()->count())->toBe(1);
});

it('lets the director fill the plan cell by cell and approve it from the page', function (): void {
    enElPanelComercial($this->directora);

    Livewire::test(PlanAnual::class, ['year' => 2026])
        ->assertSee('Todavía no hay plan para 2026')
        ->callAction('crear')
        ->assertSet('tab', 'fruta')
        ->call('guardarFruta', $this->soagrocol->id, 3, '2.830')
        ->call('guardarParametro', 'tea_percent', 3, '20,2')
        ->call('guardarVenta', $this->manuelita->id, 'cpo', 3, '484,73')
        ->set('tab', 'resumen')
        ->assertSee('Aceite: ¿alcanza para lo que se va a vender?')
        ->callAction('aprobar')
        ->assertSet('version', 'reproyeccion');

    $original = $this->planes->find($this->tenant->id, 2026, PlanVersion::Original);
    $meses = app(CommercialPlanReport::class)->plantMonths($original);
    expect($original->approved_at)->not->toBeNull()
        ->and($meses[3]['cpo'])->toBe(571.66)
        ->and($meses[3]['sales_cpo'])->toBe(484.73);

    // Mirando el original aprobado no se guarda nada.
    Livewire::test(PlanAnual::class, ['year' => 2026, 'version' => 'original'])
        ->call('guardarFruta', $this->soagrocol->id, 3, '9999');
    expect(app(CommercialPlanReport::class)->fruitPlan($original->fresh())[$this->soagrocol->id][3])->toBe(2830.0);
});

it('keeps the plan and the cross table to the administrative director', function (): void {
    enElPanelComercial($this->administrador);

    expect(PlanAnual::canAccess())->toBeFalse()
        ->and(CuadroCruce::canAccess())->toBeFalse()
        ->and(FrutaRecibida::canAccess())->toBeFalse();

    enElPanelComercial($this->directora);
    expect(PlanAnual::canAccess())->toBeTrue()
        ->and(CuadroCruce::canAccess())->toBeTrue();
});

it('imports the plan and the fruit received from the Excel workbook', function (): void {
    $ruta = libroDelPlan();

    $summary = app(PlanWorkbookImporter::class)->import($ruta, $this->tenant->id, 2026, $this->directora);

    expect($summary['plan'])->toBeTrue()
        // Palmeras del Carmen y TCC son nuevos; Soagrocol y Guariapalma ya estaban.
        ->and($summary['suppliers'])->toBe(2)
        // BioD no existía.
        ->and($summary['customers'])->toBe(1)
        ->and($summary['receptions'])->toBe(3)
        ->and($summary['warnings'])->toHaveCount(1)
        ->and($summary['warnings'][0])->toContain('TCC');

    $original = $this->planes->find($this->tenant->id, 2026, PlanVersion::Original);
    $reproyeccion = $this->planes->find($this->tenant->id, 2026, PlanVersion::Reproyeccion);
    expect($original->approved_at)->not->toBeNull()
        ->and($reproyeccion)->not->toBeNull()
        ->and((float) $original->opening_oil_tons)->toBe(392.0);

    $report = app(CommercialPlanReport::class);
    $meses = $report->plantMonths($original);
    expect($report->fruitPlan($original)[$this->soagrocol->id])->toBe([1 => 2846.0, 2 => 2681.0])
        // La TEA llega en fracción (0,2049) y se guarda en porcentaje.
        ->and($meses[1]['tea'])->toBe(20.49)
        ->and($meses[1]['capacity'])->toBe(7500.0)
        ->and($report->salesPlan($original, CommercialProduct::Cpo)[$this->manuelita->id])->toBe([1 => 450.53, 7 => 259.0])
        ->and($report->salesPlan($original, CommercialProduct::Cpo)[AnnualPlanService::UNASSIGNED])->toBe([11 => 260.0])
        ->and($report->salesPlan($original, CommercialProduct::Pko)[$this->acepalma->id])->toBe([8 => 136.0])
        ->and(Customer::query()->where('name', 'BioD')->sole()->market)->toBe(Market::Nacional)
        ->and($report->fruitReal($this->tenant->id, 2026)[$this->soagrocol->id])->toBe([1 => 2326.41, 2 => 2505.64]);

    // Otra vez: el plan no se toca y la fruta recibida no se repite.
    expect(app(PlanWorkbookImporter::class)->import($ruta, $this->tenant->id, 2026, $this->directora))
        ->toMatchArray(['plan' => false, 'suppliers' => 0, 'receptions' => 0]);
});

/**
 * Un libro con las dos hojas del plan, en la forma del de la Directora.
 */
function libroDelPlan(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'plan').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $mes = fn (array $valores): array => array_replace(array_fill(0, 12, 0), $valores);

    $writer->getCurrentSheet()->setName('Proyección Fruta');
    $writer->addRow(Row::fromValues(['PROYECCIÓN DE ENTREGAS DE FRUTA 2026 POR PROVEEDOR (tn)']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Proveedor', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC', 'TOTAL 2026']));
    $writer->addRow(Row::fromValues(['Soagrocol', ...$mes([0 => 2846, 1 => 2681])]));
    $writer->addRow(Row::fromValues(['Palmeras del Carmen', ...$mes([0 => 1302])]));
    $writer->addRow(Row::fromValues(['TCC', ...$mes([9 => -217])]));
    $writer->addRow(Row::fromValues(['TOTAL FRUTA PROYECTADA', ...$mes([0 => 4148, 1 => 2681])]));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['CAPACIDAD DE PLANTA Y EXTRACCIÓN DE ACEITE — TEA / CPO / PKO']));
    $writer->addRow(Row::fromValues(['Capacidad Planta (tn fruta)', ...$mes([0 => 7500, 1 => 7500])]));
    $writer->addRow(Row::fromValues(['TEA — Tasa de Extracción de Aceite (%)', ...$mes([0 => 0.2049, 1 => 0.1961])]));
    $writer->addRow(Row::fromValues(['CPO — Aceite de Palma Crudo (tn)', ...$mes([0 => 1090.07])]));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['ENTREGA REAL DE FRUTA POR PROVEEDOR (tn) — alimente manualmente mes a mes']));
    $writer->addRow(Row::fromValues(['Proveedor', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC', 'TOTAL']));
    $writer->addRow(Row::fromValues(['Soagrocol', ...$mes([0 => 2326.41, 1 => 2505.64])]));
    $writer->addRow(Row::fromValues(['Guariapalma', ...$mes([0 => 858.51])]));
    $writer->addRow(Row::fromValues(['TOTAL FRUTA ENTREGADA', ...$mes([0 => 3184.92])]));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['PENDIENTE POR ENTREGAR (Proyectado − Real) (tn)']));
    $writer->addRow(Row::fromValues(['Proveedor', 'ENE']));
    $writer->addRow(Row::fromValues(['Soagrocol', 519.59]));

    $writer->addNewSheetAndMakeItCurrent()->setName('Proyección Mensual');
    $writer->addRow(Row::fromValues(['PROYECCIÓN DE VENTAS DE ACEITE 2026 POR CLIENTE (tn)']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Cliente', 'Destino', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC', 'TOTAL 2026']));
    $writer->addRow(Row::fromValues(['Manuelita', 'Nacional', ...$mes([0 => 450.53, 6 => 259])]));
    $writer->addRow(Row::fromValues(['BioD', 'Nacional', ...$mes([])]));
    $writer->addRow(Row::fromValues(['Por concretar', '', ...$mes([10 => 260])]));
    $writer->addRow(Row::fromValues(['TOTAL', '', ...$mes([0 => 450.53])]));
    $writer->addRow(Row::fromValues(['Saldo anterior', 392]));
    $writer->addRow(Row::fromValues(['PKO']));
    $writer->addRow(Row::fromValues(['Cliente', 'Destino', 'ENE']));
    $writer->addRow(Row::fromValues(['Acepalma', 'Exportación', ...$mes([7 => 136])]));
    $writer->addRow(Row::fromValues(['TOTAL', '', ...$mes([7 => 136])]));

    $writer->close();

    return $ruta;
}

it('splits the oil into national and export with the director traffic light', function (): void {
    $plan = planDe2026();
    // Enero: Manuelita (nacional) 450,53 y Acepalma (exportación) 300; «por concretar» no entra.
    $this->planes->setSale($plan, $this->acepalma->id, CommercialProduct::Cpo, 1, 300);
    SalesDispatch::factory()->unloaded(60)->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2026-01-10', 'market' => 'nacional']);
    SalesDispatch::factory()->unloaded(40)->create(['customer_id' => $this->acepalma->id, 'loaded_on' => '2026-01-12', 'market' => 'exportacion']);
    SalesDispatch::factory()->unloaded(50)->create(['customer_id' => $this->acepalma->id, 'loaded_on' => '2026-02-12', 'market' => 'exportacion']);
    SalesDispatch::factory()->unloaded(50)->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2026-02-15', 'market' => 'nacional']);

    $mezcla = app(CommercialPlanReport::class)->marketMix($plan, $this->tenant->id, 2026);

    expect(round($mezcla['months'][1]['plan_share'], 4))->toBe(round(450.53 / 750.53, 4))
        ->and($mezcla['months'][1]['real_share'])->toBe(0.6)
        // Mitad y mitad: rojo.
        ->and($mezcla['months'][2]['real_share'])->toBe(0.5)
        ->and($mezcla['year']['real_national'])->toBe(110.0)
        ->and(Market::nationalShareTone(0.5))->toBe('danger')
        ->and(Market::nationalShareTone(0.51))->toBe('warning')
        ->and(Market::nationalShareTone(0.7))->toBe('success')
        ->and(Market::nationalShareTone(null))->toBeNull();

    enElPanelComercial($this->directora);

    Livewire::test(CuadroCruce::class, ['year' => 2026])
        ->assertSee('Ventas nacionales')
        ->assertSee('Aceite nacional y de exportación')
        ->assertSee('55,0 %');
});
