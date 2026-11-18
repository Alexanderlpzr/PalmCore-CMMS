<?php

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\DispatchStatus;
use App\Domain\Commercial\Enums\Market;
use App\Domain\Commercial\Services\DispatchWorkbookImporter;
use App\Filament\Pages\Inicio;
use App\Filament\Resources\Commercial\Customer\CustomerResource;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\CreateSalesDispatch;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\ListSalesDispatches;
use App\Filament\Resources\Commercial\SalesDispatch\Pages\ViewSalesDispatch;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Filament\Resources\Commercial\SalesOffer\Pages\ListSalesOffers;
use App\Filament\Resources\Commercial\SalesOffer\SalesOfferResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Carrier;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SalesDispatch;
use App\Models\SalesOffer;
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
 * El módulo comercial de la Directora Administrativa: el Excel «Proyección vs Despacho»
 * hecho pantalla. Un despacho por cargue que avanza solo con sus datos —descargue,
 * factura, pago— y descuenta el peso de llegada de su oferta mercantil.
 *
 * Hoy es el 17 de noviembre de 2026.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00'));

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $persona = function (string $rol, string $nombre): User {
        $user = User::factory()->create(['is_active' => true, 'name' => $nombre]);
        $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $user->assignRole($rol);

        return $user;
    };

    $this->directora = $persona('direccion-administrativa', 'Directora Administrativa');
    $this->administrador = $persona('administrador-general', 'Administrador');
    $this->mantenimiento = $persona('mantenimiento', 'Mantenimiento');

    $this->manuelita = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Manuelita', 'market' => Market::Nacional->value]);
    $this->acepalma = Customer::factory()->export()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acepalma']);
});

function enElPanel(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant(test()->tenant);
}

it('moves a load from in transit to paid with its own data, not with a status picker', function (): void {
    $despacho = SalesDispatch::factory()->create(['customer_id' => $this->manuelita->id, 'departure_weight_tons' => 34.26]);
    expect($despacho->status)->toBe(DispatchStatus::Cargado);

    enElPanel($this->directora);

    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->assertActionVisible('registrarDescargue')
        ->assertActionHidden('registrarFactura')
        ->callAction('registrarDescargue', [
            'unloaded_on' => '2026-11-17',
            'arrival_weight_tons' => 34.20,
            'arrival_acidity' => 4.86,
            'settlement_amount' => 145_000_000,
            'quality_bonus' => 80_461,
        ])
        ->assertHasNoActionErrors();

    expect($despacho->fresh()->status)->toBe(DispatchStatus::Descargado)
        ->and($despacho->fresh()->weightDifference())->toBe(0.06);

    // La factura propone lo liquidado más la bonificación.
    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->mountAction('registrarFactura')
        ->assertActionDataSet(['invoice_amount' => 145_080_461.0])
        ->setActionData(['invoice_number' => '980', 'invoiced_on' => '2026-11-17', 'invoice_amount' => 145_080_461])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($despacho->fresh()->status)->toBe(DispatchStatus::Facturado)
        ->and($despacho->fresh()->invoiceDifference())->toBe(0.0);

    Livewire::test(ViewSalesDispatch::class, ['record' => $despacho->getRouteKey()])
        ->callAction('registrarPago', ['paid_on' => '2026-11-17'])
        ->assertHasNoActionErrors();

    expect($despacho->fresh()->status)->toBe(DispatchStatus::Pagado);
});

it('computes the freight, the net price per ton and the shrinkage like the Excel', function (): void {
    $despacho = SalesDispatch::factory()->invoiced(145_149_131)->create([
        'customer_id' => $this->manuelita->id,
        'departure_weight_tons' => 34.26,
        'arrival_weight_tons' => 34.20,
        'freight_rate_per_ton' => 125_000,
    ]);

    // 125.000 × 34,20 = 4.275.000; (145.149.131 − 4.275.000) / 34,26.
    expect($despacho->freightCost())->toBe(4_275_000.0)
        ->and(round($despacho->netPricePerTon(), 2))->toBe(4_111_912.76)
        ->and(round($despacho->shrinkageRatio(), 5))->toBe(0.00175);
});

it('discounts the arrival weight from the offer and closes it within one ton', function (): void {
    $oferta = SalesOffer::factory()->create(['customer_id' => $this->acepalma->id, 'number' => '4800016484', 'contracted_tons' => 68]);

    SalesDispatch::factory()->unloaded(34.10)->create(['customer_id' => $this->acepalma->id, 'sales_offer_id' => $oferta->id]);
    // En tránsito: no cuenta hasta que llegue.
    SalesDispatch::factory()->create(['customer_id' => $this->acepalma->id, 'sales_offer_id' => $oferta->id, 'departure_weight_tons' => 34]);

    expect($oferta->fresh()->remainingTons())->toBe(33.9)
        ->and($oferta->fresh()->isClosed())->toBeFalse()
        ->and(SalesOffer::query()->open()->pluck('id')->all())->toBe([$oferta->id]);

    SalesDispatch::query()->where('status', DispatchStatus::Cargado->value)->first()->update(['arrival_weight_tons' => 33.2, 'unloaded_on' => '2026-11-17']);

    expect($oferta->fresh()->remainingTons())->toBe(0.7)
        ->and($oferta->fresh()->isClosed())->toBeTrue()
        ->and(SalesOffer::query()->closed()->pluck('id')->all())->toBe([$oferta->id]);
});

it('offers only the open offers of the customer for that product when registering a load', function (): void {
    $abierta = SalesOffer::factory()->create(['customer_id' => $this->acepalma->id, 'number' => 'A-1', 'contracted_tons' => 100]);
    $cerrada = SalesOffer::factory()->create(['customer_id' => $this->acepalma->id, 'number' => 'C-1', 'contracted_tons' => 34]);
    SalesDispatch::factory()->unloaded(34)->create(['customer_id' => $this->acepalma->id, 'sales_offer_id' => $cerrada->id]);
    SalesOffer::factory()->create(['customer_id' => $this->acepalma->id, 'number' => 'P-1', 'product' => CommercialProduct::Pko->value]);
    SalesOffer::factory()->create(['customer_id' => $this->manuelita->id, 'number' => 'M-1']);

    expect(array_keys(SalesDispatchResource::offerOptions($this->acepalma->id, 'cpo')))->toBe([$abierta->id])
        // Al editar, la que ya tiene sigue en la lista aunque esté cerrada.
        ->and(array_keys(SalesDispatchResource::offerOptions($this->acepalma->id, 'cpo', $cerrada->id)))->toEqualCanonicalizing([$abierta->id, $cerrada->id]);

    $transportadora = Carrier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Suracarga']);
    enElPanel($this->directora);

    Livewire::test(CreateSalesDispatch::class)
        ->fillForm([
            'product' => 'cpo',
            'loaded_on' => '2026-11-17',
            'customer_id' => $this->acepalma->id,
        ])
        ->assertFormSet(['market' => 'exportacion'])
        ->fillForm([
            'sales_offer_id' => $abierta->id,
            'carrier_id' => $transportadora->id,
            'plate' => 'xic-607',
            'remission_number' => '14500',
            'departure_weight_tons' => 34.5,
            'departure_acidity' => 4.2,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $despacho = SalesDispatch::query()->where('remission_number', '14500')->sole();
    expect($despacho->plate)->toBe('XIC607')
        ->and($despacho->status)->toBe(DispatchStatus::Cargado)
        ->and($despacho->created_by)->toBe($this->directora->id);
});

it('keeps sales to the administrative director: not the general admin nor maintenance', function (): void {
    expect($this->directora->can('viewAny', SalesDispatch::class))->toBeTrue()
        ->and($this->directora->can('create', SalesOffer::class))->toBeTrue()
        ->and($this->administrador->can('viewAny', SalesDispatch::class))->toBeFalse()
        ->and($this->mantenimiento->can('viewAny', Customer::class))->toBeFalse()
        ->and(Role::humanizeName('direccion-administrativa'))->toBe('Dirección Administrativa');

    enElPanel($this->administrador);
    expect(SalesDispatchResource::canViewAny())->toBeFalse()
        ->and(SalesOfferResource::shouldRegisterNavigation())->toBeFalse()
        ->and(CustomerResource::shouldRegisterNavigation())->toBeFalse();
});

it('lands the director on her loads with the cycle in four numbers', function (): void {
    SalesDispatch::factory()->create(['customer_id' => $this->manuelita->id]);
    SalesDispatch::factory()->invoiced(150_000_000)->create(['customer_id' => $this->manuelita->id]);
    SalesDispatch::factory()->paid()->create(['customer_id' => $this->acepalma->id]);

    enElPanel($this->directora);

    Livewire::test(Inicio::class)->assertRedirect(SalesDispatchResource::getUrl());

    Livewire::test(ListSalesDispatches::class)
        ->assertOk()
        ->assertSee('En tránsito')
        ->assertSee('Por cobrar')
        ->assertSee('$150 M')
        ->assertCanSeeTableRecords(SalesDispatch::query()->get());

    Livewire::test(ListSalesDispatches::class, ['activeTab' => DispatchStatus::Facturado->value])
        ->assertCanSeeTableRecords(SalesDispatch::query()->where('status', DispatchStatus::Facturado->value)->get())
        ->assertCanNotSeeTableRecords(SalesDispatch::query()->where('status', '!=', DispatchStatus::Facturado->value)->get());

    Livewire::test(ListSalesOffers::class)->assertOk();
});

it('imports the Excel workbook: customers, carriers, offers and loads of oil and kernel', function (): void {
    $ruta = libroDeDespachos();

    $summary = app(DispatchWorkbookImporter::class)->import($ruta, $this->tenant->id, $this->directora);

    expect($summary)->toMatchArray(['dispatches' => 6, 'skipped' => 0])
        // BioD y Palmar de Altamira se crean; Manuelita y Acepalma ya estaban.
        ->and($summary['customers'])->toBe(2)
        ->and($summary['carriers'])->toBe(1)
        // La de la hoja, la de Manuelita que nunca se anotó y la del palmiste.
        ->and($summary['offers'])->toBe(3);

    $bioD = Customer::query()->where('name', 'BioD')->sole();
    expect($bioD->aliases)->toBe(['Bio D'])
        ->and(Customer::query()->where('name', 'Palmar de Altamira')->sole()->market)->toBe(Market::Nacional);

    // «Bio D» en el Excel es BioD: no se crea otro cliente.
    expect(SalesDispatch::query()->where('customer_id', $bioD->id)->count())->toBe(2);

    $pagado = SalesDispatch::query()->where('remission_number', '8845')->sole();
    expect($pagado->status)->toBe(DispatchStatus::Pagado)
        ->and($pagado->paid_on->toDateString())->toBe('2026-02-06')
        ->and($pagado->offer->number)->toBe('3300317158')
        ->and($pagado->carrier->name)->toBe('Suracarga')
        ->and((float) $pagado->freight_rate_per_ton)->toBe(125000.0)
        ->and((float) $pagado->arrival_acidity)->toBe(4.86);

    // «Pagado» sin fecha: pagado igual.
    expect(SalesDispatch::query()->where('remission_number', '8846')->sole()->status)->toBe(DispatchStatus::Pagado);

    // El recibo que estaba en la columna de la transportadora.
    $conRecibo = SalesDispatch::query()->where('remission_number', '9002')->sole();
    expect($conRecibo->receipt_number)->toBe('134392-1')
        ->and($conRecibo->carrier_id)->toBeNull()
        // «Pagado» sin factura queda por facturar.
        ->and($conRecibo->status)->toBe(DispatchStatus::Descargado);

    // En tránsito: salió y no ha llegado.
    expect(SalesDispatch::query()->where('remission_number', '9100')->sole()->status)->toBe(DispatchStatus::Cargado);

    // La oferta que los despachos usaban sin estar en su hoja, con las toneladas de «Tn Oferta».
    $creada = SalesOffer::query()->where('number', '4500006207')->sole();
    expect((float) $creada->contracted_tons)->toBe(400.0)
        ->and($creada->customer_id)->toBe($this->manuelita->id)
        // La de palmiste tampoco estaba en la hoja.
        ->and($summary['warnings'])->toContain('2 ofertas usadas en los despachos no estaban en la hoja de ofertas y se crearon con las toneladas de «Tn Oferta»: 4500006207, 4800016340.')
        ->and($summary['warnings'])->toContain('2 cargues tenían el número de recibo en la columna de la transportadora: quedó como recibo.')
        ->and($summary['warnings'])->toContain('1 cargues decían «Pagado» sin número de factura: quedaron por facturar.');

    // El palmiste: el número de oferta sin el «.0» y la oferta de palmiste.
    $palmiste = SalesDispatch::query()->where('product', CommercialProduct::Pko->value)->sole();
    expect($palmiste->offer->number)->toBe('4800016340')
        ->and($palmiste->offer->product)->toBe(CommercialProduct::Pko)
        ->and($palmiste->customer_id)->toBe($this->acepalma->id)
        ->and($palmiste->market)->toBe(Market::Exportacion)
        ->and((float) $palmiste->invoice_amount)->toBe(39_623_291.4);

    // Importar otra vez no repite nada.
    expect(app(DispatchWorkbookImporter::class)->import($ruta, $this->tenant->id, $this->directora))
        ->toMatchArray(['dispatches' => 0, 'offers' => 0, 'customers' => 0, 'skipped' => 6]);
});

/**
 * Un libro con la forma del de la Directora: «Proyección Mensual» (los destinos),
 * «Despacho 2026» (encabezado en la fila 3), «Almendra y PKO (Maquila)» con su tabla de
 * venta de palmiste y «Ofertas Mercantiles» (encabezado en la fila 4).
 */
function libroDeDespachos(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'despachos').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $fecha = fn (string $dia): DateTimeImmutable => new DateTimeImmutable($dia);

    $writer->getCurrentSheet()->setName('Proyección Mensual');
    $writer->addRow(Row::fromValues(['PROYECCIÓN DE VENTAS DE ACEITE 2026 POR CLIENTE (tn)']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Cliente', 'Destino', 'ENE']));
    $writer->addRow(Row::fromValues(['Manuelita', 'Nacional', 450]));
    $writer->addRow(Row::fromValues(['Palmar de Altamira', 'Nacional', 0]));
    $writer->addRow(Row::fromValues(['BioD', 'Nacional', 0]));
    $writer->addRow(Row::fromValues(['Acepalma', 'Exportación', 166]));

    $writer->addNewSheetAndMakeItCurrent()->setName('Despacho 2026');
    $writer->addRow(Row::fromValues(['DESPACHOS DE ACEITE 2026']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Fecha Cargue', '# Cargue', 'Placa', 'Estado Descargue', 'Fecha Descargue', 'Remisión', 'Recibo', 'Cliente', 'Transportadora', 'N° de Factura', 'Fecha Factura', 'Peso Salida (tn)', 'Peso Llegada (tn)', 'Dif', 'Diferencia', 'Flete ($)', 'Bon. venta aceite ($)', 'Total ($)', 'Valor Factura ($)', 'Diferencia Valor ($)', 'Estado Pago', 'Fecha Pago', 'Oferta Mercantil', 'Tn Oferta', 'Saldo (original)', 'Destino', 'Flete x tn ($)', 'Flete total ($)', 'Precio Promedio por tn ($)', 'Acidez salida (%)', 'Acidez Llegada (%)']));
    //                       A                     B   C         D             E                     F     G    H              I              J    K                     L      M      N     O     P   Q       R            S            T   U         V                     W             X    Y      Z           AA      AB  AC  AD    AE
    $writer->addRow(Row::fromValues([$fecha('2026-01-09'), '', 'XIC607', 'Descargado', $fecha('2026-01-10'), 8845, '', 'Manuelita', 'Suracarga', 980, $fecha('2026-01-14'), 34.26, 34.20, 0.06, 0.001, '', 80461, 145068670, 145149131, '', 'Pagado', $fecha('2026-02-06'), '3300317158', 400, 365.8, 'Nacional', 125000, '', '', 4.88, 4.86]));
    $writer->addRow(Row::fromValues([$fecha('2026-01-09'), '', 'SQM007', 'Descargado', $fecha('2026-01-10'), 8846, '', 'Manuelita', 'Suracarga', 982, $fecha('2026-01-14'), 32.94, 32.81, 0.13, 0.003, '', '', 139148408, 139148408, '', 'Pagado', '', '3300317158', 400, 332.99, 'Nacional', 125000, '', '', 4.96, 4.9]));
    $writer->addRow(Row::fromValues([$fecha('2026-03-02'), '', 'WXK136', 'Descargado', $fecha('2026-03-03'), 9001, '', 'Bio D', '', 1100, $fecha('2026-03-05'), 34.98, 34.92, 0.06, 0.001, '', '', 148279188, 148279188, '', '', '', '', '', '', '', '', '', '', 4.9, 4.82]));
    $writer->addRow(Row::fromValues([$fecha('2026-03-04'), '', 'USC519', 'Descargado', $fecha('2026-03-05'), 9002, '', 'BioD', '134392-1', '', '', 29.02, 29.01, 0.01, 0.0003, '', '', '', '', '', 'Pagado', '', '', '', '', 'Nacional', '', '', '', 4.86, 4.93]));
    $writer->addRow(Row::fromValues([$fecha('2026-11-16'), '', 'TFK742', 'Cargó', '', 9100, '', 'Manuelita', '', '', '', 34.5, '', '', '', '', '', '', '', '', '', '', '4500006207', 400, '', 'Nacional', 125000, '', '', 4.5]));
    // Las filas vacías del final que el Excel deja con fórmulas en cero.
    $writer->addRow(Row::fromValues(['', '', '', '', '', '', '', '', '', '', '', 0, 0]));

    $writer->addNewSheetAndMakeItCurrent()->setName('Almendra y PKO (Maquila)');
    $writer->addRow(Row::fromValues(['PAJUIL — ALMENDRA: MAQUILA (TORTA) Y VENTA DE PALMISTE (PKO) 2026']));
    $writer->addRow(Row::fromValues(['MAQUILA — PRODUCCIÓN DE TORTA (Y ACEITE ESTIMADO) 2026']));
    $writer->addRow(Row::fromValues(['Fecha Cargue', 'Placa', 'Estado Descargue', 'Fecha Descargue', 'Remisión', 'Peso Salida (tn)']));
    $writer->addRow(Row::fromValues([$fecha('2026-01-03'), 'TFK742', '', $fecha('2026-01-04'), 8841, 35.47]));
    $writer->addRow(Row::fromValues(['VENTA PALMISTE (PKO) 2026 — VENTAS COMERCIALES REALES']));
    $writer->addRow(Row::fromValues(['Fecha Cargue', 'Placa', 'Estado Descargue', 'Fecha Descargue', 'Remisión', 'Transportadora', 'N° Factura', 'Fecha Factura', 'Peso Salida (tn)', 'Peso Llegada (tn)', 'Dif', 'Valor Factura ($)', 'Estado Pago', 'Fecha Pago', 'Oferta Mercantil', 'Tn Oferta', 'Saldo', 'Destino', 'Flete x tn ($)']));
    $writer->addRow(Row::fromValues([$fecha('2026-01-13'), 'LGL611', 'Descargado', $fecha('2026-01-15'), 1542, '134026-1', 987, $fecha('2026-01-19'), 32.95, 7.59, '', 39623291.4, 'Pagado', $fecha('2026-01-29'), '4800016340.0', 23, 0, 'Exportación', 255000]));
    $writer->addRow(Row::fromValues(['RESUMEN MENSUAL 2026 (tn)']));
    $writer->addRow(Row::fromValues(['PKO (Palmiste) vendido/despachado (tn)', 169.61]));

    $writer->addNewSheetAndMakeItCurrent()->setName('Ofertas Mercantiles');
    $writer->addRow(Row::fromValues(['PAJUIL — OFERTAS MERCANTILES DE ACEITE 2026']));
    $writer->addRow(Row::fromValues(['Celdas amarillas: digite aquí una oferta mercantil NUEVA']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['Cliente', 'N° Oferta Mercantil', 'Tn Ofertadas (contratadas)']));
    $writer->addRow(Row::fromValues(['Manuelita', '3300317158', 400]));
    $writer->addRow(Row::fromValues(['TOTAL', '', 400]));

    $writer->close();

    return $ruta;
}
