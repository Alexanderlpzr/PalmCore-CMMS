<?php

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Services\AnnualPlanService;
use App\Domain\Commercial\Services\MonthlyManagementReport;
use App\Domain\Reports\Excel\ManagementReportExcel;
use App\Domain\Reports\Services\ManagementReportPdfService;
use App\Filament\Pages\CuadroCruce;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Customer;
use App\Models\FruitSupplier;
use App\Models\SalesDispatch;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\PermissionRegistrar;

/*
 * El informe mensual de gerencia: el mes elegido y lo acumulado del año hasta él, en PDF
 * y en Excel, con las mismas cifras del cuadro cruce.
 *
 * Hoy es el 18 de noviembre de 2026; el informe es de marzo.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00'));

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $this->directora = User::factory()->create(['is_active' => true]);
    $this->directora->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->directora->assignRole('direccion-administrativa');

    $this->manuelita = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Manuelita']);
    $soagrocol = FruitSupplier::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Soagrocol']);

    $planes = app(AnnualPlanService::class);
    $plan = $planes->createOriginal($this->tenant->id, 2026, null);
    $planes->setSale($plan, $this->manuelita->id, CommercialProduct::Cpo, 2, 100);
    $planes->setSale($plan, $this->manuelita->id, CommercialProduct::Cpo, 3, 200);
    $planes->setSale($plan, $this->manuelita->id, CommercialProduct::Cpo, 4, 300);
    $planes->setFruit($plan, $soagrocol->id, 3, 1000);
    $planes->setMonthParameter($plan, 3, 'tea_percent', 20);
    $planes->setReception($this->tenant->id, $soagrocol->id, 2026, 3, 900, null);

    SalesDispatch::factory()->paid()->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2026-02-10', 'arrival_weight_tons' => 90, 'invoiced_on' => '2026-02-12', 'invoice_amount' => 360_000_000]);
    SalesDispatch::factory()->invoiced(800_000_000)->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2026-03-05', 'arrival_weight_tons' => 190, 'invoiced_on' => '2026-03-08']);
    // Abril no entra en el informe de marzo.
    SalesDispatch::factory()->unloaded(34)->create(['customer_id' => $this->manuelita->id, 'loaded_on' => '2026-04-02']);
});

it('puts the month and the year to date side by side', function (): void {
    $datos = app(MonthlyManagementReport::class)->build($this->tenant->id, 2026, 3);
    $s = $datos['summary'];

    expect($datos['periodLabel'])->toBe('Marzo de 2026')
        ->and($s['oil_month_projected'])->toBe(200.0)
        ->and($s['oil_month_dispatched'])->toBe(190.0)
        ->and($s['oil_month_ratio'])->toBe(0.95)
        // Febrero y marzo; abril no.
        ->and($s['oil_ytd_projected'])->toBe(300.0)
        ->and($s['oil_ytd_dispatched'])->toBe(280.0)
        ->and($s['fruit_month_received'])->toBe(900.0)
        ->and($s['fruit_month_ratio'])->toBe(0.9)
        ->and($s['invoiced_month'])->toBe(800_000_000.0)
        ->and($s['invoiced_ytd'])->toBe(1_160_000_000.0)
        ->and(round($s['oil_price_month']))->toBe(round(800_000_000 / 190))
        ->and($s['receivable'])->toBe(800_000_000.0)
        ->and($datos['plant']['expected_cpo'])->toBe(200.0)
        ->and($datos['plant']['real_cpo_estimate'])->toBe(180.0)
        ->and($datos['products']['cpo']['rows'][$this->manuelita->id]['ytd_ratio'])->toBe(280 / 300);
});

it('prints the PDF and the Excel with the same figures', function (): void {
    $pdf = app(ManagementReportPdfService::class)->generate($this->tenant, 2026, 3);
    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(app(ManagementReportPdfService::class)->filename(2026, 3))->toBe('INFORME-GERENCIA-2026-03.pdf');

    $ruta = app(ManagementReportExcel::class)->build($this->tenant, 2026, 3)->close();
    $reader = new Reader;
    $reader->open($ruta);
    $hojas = [];
    $resumen = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $hojas[] = $sheet->getName();

        if ($sheet->getName() === 'Resumen') {
            foreach ($sheet->getRowIterator() as $row) {
                $valores = array_map(fn ($c) => $c instanceof FormulaCell ? $c->getComputedValue() : $c->getValue(), $row->getCells());
                $resumen[$valores[0] ?? ''] = array_slice($valores, 1);
            }
        }
    }

    $reader->close();

    expect($hojas)->toBe(['Resumen', 'Aceite por cliente', 'Facturación', 'Cartera', 'Fruta', 'Ofertas abiertas'])
        ->and($resumen['Aceite despachado (tn)'])->toEqual([190, 280])
        ->and($resumen['Facturado'])->toEqual([800000000, 1160000000]);
});

it('lets the director download it from the cross table', function (): void {
    $this->actingAs($this->directora);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(CuadroCruce::class, ['year' => 2026])
        ->callAction('informePdf', ['month' => 3])
        ->assertFileDownloaded('INFORME-GERENCIA-2026-03.pdf');

    Livewire::test(CuadroCruce::class, ['year' => 2026])
        ->callAction('informeExcel', ['month' => 3])
        ->assertFileDownloaded('INFORME-GERENCIA-2026-03.xlsx');
});
