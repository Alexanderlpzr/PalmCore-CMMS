<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Domain\Reports\Services\DashboardPdfService;
use App\Domain\Reports\Services\EnergiaPdfService;
use App\Domain\Reports\Services\PeriodPdfReport;
use App\Domain\Reports\Services\PresupuestoPdfService;
use App\Domain\Reports\Services\ProductividadPdfService;
use App\Filament\Pages\ConsumoDeEnergia;
use App\Models\EnergyMeter;
use App\Models\MaintenanceBudget;
use App\Models\MaintenanceBudgetExpense;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Los cuatro informes de Indicadores que se llevan a la reunión ejecutiva.
 *
 * El PDF se genera de verdad —sin simular DomPDF— porque lo que puede romperse aquí es la
 * plantilla: una variable que la vista espera y el servicio no manda no la atrapa ningún
 * test de datos, y sí sale en la reunión.
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->actingAs($this->user);

    $this->calendario = app(ProductionCalendarService::class);
});

// ── Que los cuatro salgan ────────────────────────────────────────────────────

it('emite los cuatro informes como PDF de verdad', function (string $servicio, string $prefijo): void {
    $bytes = app($servicio)->generate(
        $this->plant,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($bytes)->toStartWith('%PDF')
        ->and(app($servicio)->filename($this->plant, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')))
        ->toBe($prefijo.'-2026-08.pdf');
})->with([
    'productividad' => [ProductividadPdfService::class, 'PRD'],
    'energía' => [EnergiaPdfService::class, 'ENE'],
    'presupuesto' => [PresupuestoPdfService::class, 'PRE'],
    'dashboard' => [DashboardPdfService::class, 'IND'],
]);

it('nombra el archivo con los dos extremos cuando el período abarca varios meses', function (): void {
    $nombre = app(EnergiaPdfService::class)->filename(
        $this->plant,
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-06-30'),
    );

    expect($nombre)->toBe('ENE-2026-03_2026-06.pdf');
});

// ── El rótulo del período ────────────────────────────────────────────────────

it('dice el período como lo diría una persona', function (string $desde, string $hasta, string $esperado): void {
    expect(PeriodPdfReport::periodLabel(Carbon::parse($desde), Carbon::parse($hasta)))->toBe($esperado);
})->with([
    // Un mes se dice como un mes, no «Agosto – Agosto».
    'un mes' => ['2026-08-01', '2026-08-31', 'Agosto de 2026'],
    'meses del mismo año' => ['2026-03-01', '2026-06-30', 'Marzo – junio de 2026'],
    'a caballo entre dos años' => ['2025-11-01', '2026-02-28', 'Noviembre de 2025 – febrero de 2026'],
]);

// ── La regla que un informe ejecutivo no puede romper ────────────────────────

it('calcula el KWh/RFF del rango sobre los totales, no promediando los meses', function (): void {
    // Dos meses deliberadamente desiguales: uno flojo y caro por tonelada, otro de plena
    // cosecha y barato. Promediar los dos ratios le daría el mismo peso a cada uno.
    $turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);
    $lecturas = app(EnergyMeterReadingService::class);

    // Marzo: 1.000 kWh sobre 100 t → 10 kWh/t
    $lecturas->record($turbina, 0, $this->user, Carbon::parse('2026-03-01'));
    $lecturas->record($turbina, 1_000, $this->user, Carbon::parse('2026-03-31'));
    $this->calendario->upsertDay($this->plant, Carbon::parse('2026-03-15'), 20, 100);

    // Abril: 2.000 kWh sobre 900 t → 2,22 kWh/t
    $lecturas->record($turbina, 3_000, $this->user, Carbon::parse('2026-04-30'));
    $this->calendario->upsertDay($this->plant, Carbon::parse('2026-04-15'), 20, 900);

    $resumen = app(PlantKpiService::class)->energySummary(
        $this->plant,
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-04-30'),
    );

    // 3.000 kWh / 1.000 t = 3,00 — y no (10 + 2,22) / 2 = 6,11, que es lo que daría
    // promediar. Un mes flojo no puede pesar lo mismo que uno de plena cosecha.
    expect($resumen['kwh_per_ton'])->toBe(3.0)
        ->and($resumen['kwh_per_ton'])->not->toBe(6.11);
});

// ── Lo que un informe no debe afirmar ────────────────────────────────────────

it('avisa en el informe que cuatro bloques no son del período', function (): void {
    $vista = view('reports.indicadores-dashboard', [
        ...invadeBranding($this->plant),
        'porTipo' => [], 'porCausa' => [], 'porSeccion' => [], 'porEquipo' => [],
        'horasPlanta' => 0.0, 'horasTotales' => 0.0, 'porCategoria' => [],
        'pareto' => [], 'paretoVentana' => 'últimos 12 meses',
        'cumplimiento' => ['total' => 0, 'on_schedule' => 0, 'overdue' => 0, 'compliance' => null],
        'cumplimientoVentana' => 'estado a hoy',
        'planificado' => ['preventive' => 0, 'corrective' => 0, 'total' => 0, 'preventive_pct' => null],
        'planificadoVentana' => 'últimos 12 meses',
        'costoPorEquipo' => [], 'costoVentana' => 'histórico completo',
        'hasData' => false,
    ])->render();

    // Sin este aviso, quien lee un informe titulado «Agosto de 2026» daría por hecho que
    // el Pareto de doce meses es del mes, y discutiría sobre una cifra que no es la que cree.
    expect($vista)->toContain('No son cifras del período')
        ->and($vista)->toContain('estado a hoy');
});

it('no imprime ceros cuando el período no tiene nada', function (): void {
    $bytes = app(ProductividadPdfService::class)->generate(
        $this->plant,
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
    );

    // Cero afirma que la planta estuvo parada; vacío dice que nadie cargó la jornada.
    expect($bytes)->toStartWith('%PDF');

    $vista = view('reports.indicadores-productividad', [
        ...invadeBranding($this->plant),
        'kpis' => app(PlantKpiService::class)->calculate(
            $this->plant, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'),
        ),
        'baseHours' => 0.0,
        'hasData' => false,
    ])->render();

    expect($vista)->toContain('No hay jornadas cargadas');
});

// ── Aislamiento ──────────────────────────────────────────────────────────────

it('no mezcla el gasto de otra planta en el informe de presupuesto', function (): void {
    $otra = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    MaintenanceBudget::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 8, 'amount' => 1_000_000,
    ]);

    foreach ([[$this->plant, 200_000], [$otra, 999_999]] as [$planta, $monto]) {
        MaintenanceBudgetExpense::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'plant_id' => $planta->id,
            'expense_date' => '2026-08-10', 'amount' => $monto,
            'category' => 'repuestos', 'description' => 'prueba',
        ]);
    }

    $vista = view('reports.indicadores-presupuesto', [
        ...invadeBranding($this->plant),
        ...invadeData(PresupuestoPdfService::class, $this->plant, '2026-08-01', '2026-08-31'),
    ])->render();

    expect($vista)->toContain('200.000')
        ->and($vista)->not->toContain('999.999');
});

// ── Utilidades ───────────────────────────────────────────────────────────────

/**
 * La identidad del documento, sin QR ni logo: la vista solo necesita que existan.
 *
 * @return array<string, mixed>
 */
function invadeBranding(Plant $plant): array
{
    return [
        'plant' => $plant,
        'from' => Carbon::parse('2026-08-01'),
        'to' => Carbon::parse('2026-08-31'),
        'periodLabel' => 'Agosto de 2026',
        'tenant' => Tenant::withoutGlobalScopes()->find($plant->tenant_id),
        'logoBase64' => null,
        'documentNumber' => 'TEST-0001',
        'documentVersion' => '1.0',
        'qrBase64' => null,
        'generatedAt' => Carbon::parse('2026-09-01 10:00'),
    ];
}

/**
 * Los datos de un informe, sin pasar por DomPDF.
 *
 * @return array<string, mixed>
 */
function invadeData(string $servicio, Plant $plant, string $desde, string $hasta): array
{
    $metodo = new ReflectionMethod($servicio, 'data');
    $metodo->setAccessible(true);

    return $metodo->invoke(app($servicio), $plant, Carbon::parse($desde), Carbon::parse($hasta));
}

// ── El botón de la pantalla ──────────────────────────────────────────────────

it('el botón de la pantalla descarga el informe del período que tiene puesto el filtro', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $respuesta = Livewire::test(ConsumoDeEnergia::class)
        ->set('filters', [
            'plant_id' => $this->plant->id,
            'preset' => 'month',
            'year' => 2026,
            'month' => 8,
        ])
        ->callAction('descargarInforme')
        ->assertHasNoActionErrors();

    // El nombre lleva el período dentro: si la acción resolviera la ventana por su cuenta
    // en vez de leer el filtro, aquí saldría el mes en curso y nadie lo notaría hasta la
    // reunión. Ese desajuste exacto ya ocurrió una vez con el rótulo de la pantalla.
    $descarga = $respuesta->effects['download'] ?? null;

    expect($descarga)->not->toBeNull()
        ->and($descarga['name'] ?? '')->toBe('ENE-2026-08.pdf');
});

it('no emite nada cuando el filtro no tiene planta', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $pagina = new ConsumoDeEnergia;
    $pagina->filters = ['plant_id' => null];

    $metodo = new ReflectionMethod(ConsumoDeEnergia::class, 'plantaDelInforme');
    $metodo->setAccessible(true);

    expect($metodo->invoke($pagina))->toBeNull();
});

it('no deja emitir el informe de una planta de otro tenant', function (): void {
    $ajeno = Tenant::factory()->create();
    $plantaAjena = Plant::factory()->create(['tenant_id' => $ajeno->id]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $pagina = new ConsumoDeEnergia;
    $pagina->filters = ['plant_id' => $plantaAjena->id];

    $metodo = new ReflectionMethod(ConsumoDeEnergia::class, 'plantaDelInforme');
    $metodo->setAccessible(true);

    // Aceptar el id que llegue del navegador dejaría emitir un informe firmado con los
    // datos de otra empresa.
    expect($metodo->invoke($pagina))->toBeNull();
});

// ── La geometría de los gráficos ─────────────────────────────────────────────

/**
 * Un gráfico que miente es peor que ninguno: en la reunión se discute la barra, no la
 * tabla. Estos tests no miran si es bonito —eso hay que verlo— sino si el dibujo dice
 * lo mismo que el número.
 */
it('reparte la barra de fuentes en proporción a los kWh, y suma cien', function (): void {
    $vista = view('reports.indicadores-energia', [
        ...invadeBranding($this->plant),
        'resumen' => [
            'kwh_grid' => 250_000.0, 'kwh_genset' => 250_000.0, 'kwh_turbine' => 500_000.0,
            'kwh_total' => 1_000_000.0, 'processed_tons' => 1_000.0,
            'kwh_per_ton' => 1000.0, 'clean_energy_percentage' => 50.0,
        ],
        'meses' => [],
        'hasData' => true,
    ])->render();

    // Sobre las celdas de la barra, no sobre el documento: buscar «width» a secas atrapaba
    // primero las reglas del <style>, y el test pasaba midiendo la hoja de estilos.
    preg_match_all('/<td class="fill-[a-z]+"\s*
?\s*style="width:\s*([\d.]+)%/', $vista, $anchos);
    $partes = array_map('floatval', $anchos[1]);

    // 25 + 25 + 50. Si los segmentos no suman cien, la barra dibuja una composición que
    // no existe — y es lo único que se mira de un vistazo.
    expect($partes)->toBe([25.0, 25.0, 50.0])
        ->and(array_sum($partes))->toBe(100.0);
});

it('no dibuja la barra de presupuesto más allá del cien por cien', function (): void {
    $vista = view('reports.indicadores-presupuesto', [
        ...invadeBranding($this->plant),
        'meses' => [], 'presupuesto' => 100.0, 'gastado' => 130.0, 'restante' => -30.0,
        'porcentaje' => 130.0, 'excedido' => true, 'porCategoria' => [], 'gastos' => 3,
    ])->render();

    preg_match('/chart-fill[^"]*"\s*\n?\s*style="height:14px; width:\s*(\d+)%/', $vista, $m);

    // Una barra al 130% se sale de su caja y deja de leerse. Se llena en rojo y el número
    // de al lado dice cuánto se pasó, que es donde vive esa información.
    expect((int) ($m[1] ?? 0))->toBe(100)
        ->and($vista)->toContain('fill-bad');
});

it('escala las barras contra la mayor, sin inventar una escala cuando todo es cero', function (): void {
    $conDatos = view('reports.partials.chart-bars', ['filas' => [
        ['name' => 'A', 'value' => 100, 'text' => '100'],
        ['name' => 'B', 'value' => 50, 'text' => '50'],
        ['name' => 'C', 'value' => 0, 'text' => '0'],
    ]])->render();

    preg_match_all('/width:\s*([\d.]+)%/', $conDatos, $m);

    // La mayor manda: 100 → 100%, 50 → 50%. El cero se queda en cero y no en el mínimo
    // de 1,5%, que sugeriría un valor pequeño donde no hay ninguno.
    expect(array_map('floatval', $m[1]))->toBe([100.0, 50.0, 0.0]);

    $enCero = view('reports.partials.chart-bars', ['filas' => [
        ['name' => 'A', 'value' => 0, 'text' => '0'],
        ['name' => 'B', 'value' => 0, 'text' => '0'],
    ]])->render();

    preg_match_all('/width:\s*([\d.]+)%/', $enCero, $m2);

    // Si todo vale cero no hay nada que comparar: estirarlas al máximo sería dibujar una
    // diferencia inexistente.
    expect(array_map('floatval', $m2[1]))->toBe([0.0, 0.0]);
});

it('deja el hueco en la serie mensual cuando un mes no tiene dato', function (): void {
    $html = view('reports.partials.chart-columns', ['filas' => [
        ['label' => 'Mar', 'value' => 10.0, 'text' => '10'],
        ['label' => 'Abr', 'value' => null, 'text' => '—'],
        ['label' => 'May', 'value' => 5.0, 'text' => '5'],
    ]])->render();

    // Un mes sin dato no dibuja columna: una de altura cero se leería como un mes en que
    // no se consumió nada, que es una afirmación distinta.
    preg_match_all('/chart-col\s+[^"]*"\s*\n?\s*style="height:\s*(\d+)px/', $html, $m);

    expect(array_map('intval', $m[1]))->toBe([70, 35])
        ->and(substr_count($html, 'chart-col '))->toBe(2);
});

// ── Que el color llegue al papel ─────────────────────────────────────────────

/**
 * Los rellenos que de verdad quedaron dibujados en el PDF, en hexadecimal.
 *
 * Mira el flujo de contenido, no el HTML, y esa es toda la razón de existir: un fallo de
 * cascada en la hoja de estilos no se ve en el marcado —la clase correcta está puesta— y
 * solo aparece cuando el motor decide qué regla gana. Así pasó: `.chart-col` fijaba su
 * propio fondo y ganaba a `.fill-bad` por ir después, y los meses excedidos del informe
 * de presupuesto salían verdes mientras el pie prometía que saldrían rojos.
 *
 * @return list<string>
 */
function rellenosDelPdf(string $bytes): array
{
    $contenido = '';

    preg_match_all('/stream\r?\n(.*?)endstream/s', $bytes, $flujos);

    foreach ($flujos[1] as $flujo) {
        $inflado = @gzuncompress($flujo) ?: @gzinflate(substr($flujo, 2));

        if ($inflado !== false && $inflado !== '') {
            $contenido .= $inflado;
        }
    }

    preg_match_all('/([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+rg\b/', $contenido, $m, PREG_SET_ORDER);

    return array_values(array_unique(array_map(
        fn (array $c): string => sprintf('#%02x%02x%02x', (int) round($c[1] * 255), (int) round($c[2] * 255), (int) round($c[3] * 255)),
        $m,
    )));
}

it('pinta de rojo en el papel el mes que se pasó del presupuesto', function (): void {
    // Los números están elegidos para que el rojo NO pueda venir de otro sitio: el rango
    // entero queda dentro del presupuesto —así la barra de ejecución sale verde— pero
    // agosto solo se pasa del suyo. El único rojo posible es el de su columna.
    //
    // La primera versión de este test gastaba de más en el total, la barra de arriba se
    // ponía roja, y el test pasaba igual con el fallo puesto. Un test que no distingue
    // las dos situaciones no prueba nada.
    foreach ([7, 8] as $mes) {
        MaintenanceBudget::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
            'year' => 2026, 'month' => $mes, 'amount' => 1_000_000,
        ]);
    }

    foreach ([['2026-07-10', 100_000], ['2026-08-10', 1_200_000]] as [$fecha, $monto]) {
        MaintenanceBudgetExpense::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
            'expense_date' => $fecha, 'amount' => $monto,
            'category' => 'repuestos', 'description' => 'prueba',
        ]);
    }

    $rellenos = rellenosDelPdf(app(PresupuestoPdfService::class)->generate(
        $this->plant,
        Carbon::parse('2026-07-01'),
        Carbon::parse('2026-08-31'),
    ));

    // 1.300.000 sobre 2.000.000: el rango va al 65%, dentro. Agosto, 1.200.000 sobre
    // 1.000.000, no.
    expect($rellenos)->toContain('#dc2626');
});

it('pinta en el papel la barra repartida de las fuentes de energía', function (): void {
    $turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);
    $lecturas = app(EnergyMeterReadingService::class);
    $lecturas->record($turbina, 0, $this->user, Carbon::parse('2026-08-01'));
    $lecturas->record($turbina, 50_000, $this->user, Carbon::parse('2026-08-31'));
    $this->calendario->upsertDay($this->plant, Carbon::parse('2026-08-15'), 20, 500);

    $rellenos = rellenosDelPdf(app(EnergiaPdfService::class)->generate(
        $this->plant,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    ));

    // El verde de la turbina, dibujado. Sin barras el PDF solo tendría los grises del
    // texto y las tablas.
    expect($rellenos)->toContain('#059669');
});
