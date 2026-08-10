<?php

use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageReason;
use App\Domain\Assets\Services\DowntimeLogImporter;
use App\Domain\Assets\Services\DowntimeService;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->importer = app(DowntimeLogImporter::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actor = User::factory()->create();

    $this->turbina = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'A10SPG.26.03',
        'name' => 'Turbina Shinko RB-4 950 KVA',
    ]);

    $this->prensa = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'A05EXT.05.01',
        'name' => 'Prensa de Doble Tornillo',
    ]);
});

/** Escribe un CSV con los encabezados de la planilla y las filas que se le pasen. */
function planilla(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'paros').'.csv';
    $fh = fopen($path, 'w');

    fputcsv($fh, ['Fecha', 'Día', 'Mes', 'Hora inicio', 'Hora fin', 'Tiempo paro (horas)',
        'Tipo I', 'Tipo II', 'Sección', 'Equipo', 'Causa de falla / Observación',
        'Responsable diligenciamiento'], escape: '');

    foreach ($rows as $row) {
        fputcsv($fh, $row, escape: '');
    }

    fclose($fh);

    return $path;
}

/**
 * Una fila de la planilla. Los valores por defecto son los de un paro corriente,
 * para que cada prueba solo escriba lo que le importa.
 */
function fila(array $overrides = []): array
{
    return array_values([...[
        'fecha' => '2026-03-04',
        'dia' => 'miércoles',
        'mes' => 'marzo',
        'inicio' => '08:00',
        'fin' => '09:00',
        'horas' => '1.000000',
        'tipo1' => 'Operativa',
        'tipo2' => 'Atascamiento',
        'seccion' => 'Extracción',
        'equipo' => 'Prensa de Doble Tornillo',
        'causa' => 'ATASCAMIENTO EN LA PRENSA P15',
        'responsable' => '',
    ], ...$overrides]);
}

/** @return array{imported: int, skipped: int, rows: int, errors: list<string>, unmatched: array<string, int>} */
function importar(array $rows, ...$args): array
{
    return test()->importer->import(planilla($rows), test()->plant, test()->actor, ...$args);
}

// ── Lo básico ────────────────────────────────────────────────────────────────

it('imports a stoppage with its equipment, section and cause', function (): void {
    $result = importar([fila()]);

    expect($result['imported'])->toBe(1)
        ->and($result['errors'])->toBe([]);

    $paro = EquipmentDowntimeEvent::withoutGlobalScopes()->sole();

    expect($paro->equipment_id)->toBe($this->prensa->id)
        ->and($paro->section)->toBe(PlantSection::Extraccion)
        ->and($paro->stoppage_reason)->toBe(StoppageReason::Atascamiento)
        ->and($paro->stoppage_cause)->toBe('ATASCAMIENTO EN LA PRENSA P15')
        ->and($paro->duration_minutes)->toBe(60)
        ->and($paro->source)->toBe('import');
});

it('closes the stoppage instead of leaving it open', function (): void {
    // Es un histórico: si entrara abierto, la planta aparecería parada desde marzo
    // y ningún paro posterior de ese equipo podría registrarse.
    importar([fila()]);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->sole()->isOngoing())->toBeFalse();
});

// ── La regla del horario ─────────────────────────────────────────────────────

it('takes the end time from the duration, not from the end column', function (): void {
    // La columna «Hora fin» del Excel arrastra fechas de 1900 y días equivocados;
    // «Tiempo paro (horas)» cuadra en las 605 filas del histórico. Aquí la hora de
    // fin miente a propósito y el paro tiene que durar lo que dice la duración.
    importar([fila(['fin' => '23:56', 'horas' => '2.500000'])]);

    $paro = EquipmentDowntimeEvent::withoutGlobalScopes()->sole();

    expect($paro->duration_minutes)->toBe(150)
        ->and($paro->ended_at->format('Y-m-d H:i'))->toBe('2026-03-04 10:30');
});

it('rolls a stoppage that ends at midnight into the next day', function (): void {
    // La planta corta el paro a las 24:00 y lo continúa al día siguiente a las 00:01.
    importar([fila(['inicio' => '20:00', 'horas' => '4.000000'])]);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->sole()->ended_at->format('Y-m-d H:i'))
        ->toBe('2026-03-05 00:00');
});

it('loads rows in clock order even when the sheet is not sorted', function (): void {
    // En la planilla real el paro de las 20:54 aparece antes que el de las 07:04.
    // Si se cargaran en el orden del archivo, el segundo chocaría con el primero.
    $result = importar([
        fila(['inicio' => '20:54', 'horas' => '0.500000']),
        fila(['inicio' => '07:04', 'horas' => '0.500000']),
        fila(['inicio' => '12:47', 'horas' => '0.500000']),
    ]);

    expect($result['imported'])->toBe(3)
        ->and($result['errors'])->toBe([]);
});

it('loads history behind stoppages the plant already registered by hand', function (): void {
    // El caso real: la planta ya tenía cargados los paros de este mes a mano y se
    // le suben diez meses de histórico anteriores. Si el importador abriera el
    // paro antes de cerrarlo, mientras está abierto se consideraría vigente hasta
    // el infinito y chocaría con el paro de agosto aunque no se crucen — la
    // turbina perdía así sus ciento cincuenta y seis filas.
    app(DowntimeService::class)->register([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->turbina->id,
        'stoppage_reason' => StoppageReason::ArranqueDePlanta->value,
        'started_at' => '2026-08-03 10:55',
        'ended_at' => '2026-08-03 11:00',
    ], $this->actor);

    $result = importar([
        fila(['fecha' => '2026-03-04', 'equipo' => 'Turbina']),
        fila(['fecha' => '2026-07-15', 'equipo' => 'Turbina']),
    ]);

    expect($result['imported'])->toBe(2)
        ->and($result['errors'])->toBe([]);
});

// ── El Tipo I de la planta se respeta ────────────────────────────────────────

it('keeps the Tipo I the plant wrote even when it contradicts the physical cause', function (): void {
    // En la planilla, «falla mecánica» sale 52 veces como Operativa y 27 como
    // Mantenimiento, según quién asumió el paro. Esa contradicción es el dato que
    // mide la brecha de atribución: normalizarla la borraría.
    importar([fila([
        'tipo1' => 'Operativa',
        'tipo2' => 'Falla mecánica',
        'causa' => 'SE ROMPIO EL EJE LARGO',
    ])]);

    $paro = EquipmentDowntimeEvent::withoutGlobalScopes()->sole();

    expect($paro->reported_type)->toBe(ReportedStoppageType::Operational)
        ->and($paro->stoppage_category)->toBe(StoppageCategory::Mechanical);
});

it('does not count a plant shutdown as planned maintenance', function (): void {
    // «Apagado planta» se reporta como Programada, pero apagar para vaciar el
    // preclarificador no es mantenimiento. Si entrara como planeado sumaría a las
    // horas de aseo y subiría la eficiencia descontando tiempo disponible.
    importar([fila([
        'tipo1' => 'Programada',
        'tipo2' => 'Apagado planta',
        'seccion' => 'Planta general',
        'equipo' => '',
        'causa' => 'DESOCUPAR EL PRECLARIFICADOR',
    ])]);

    $paro = EquipmentDowntimeEvent::withoutGlobalScopes()->sole();

    expect($paro->was_planned)->toBeFalse()
        ->and($paro->stoppage_category)->toBe(StoppageCategory::Operational)
        ->and($paro->reported_type)->toBe(ReportedStoppageType::Scheduled);
});

it('does count programmed maintenance as planned', function (): void {
    importar([fila([
        'tipo1' => 'Programada',
        'tipo2' => 'Mantenimiento programado',
        'seccion' => 'Planta general',
        'equipo' => 'Mantenimiento programado',
        'causa' => 'MANTENIMIENTO PROGRAMADO EN LA PLANTA',
    ])]);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->sole()->was_planned)->toBeTrue();
});

// ── La columna «Equipo» no siempre trae un equipo ────────────────────────────

it('resolves an equipment the sheet names differently', function (): void {
    // 156 de las 605 filas dicen solo «Turbina». Sin el alias quedarían huérfanas.
    importar([fila(['equipo' => 'Turbina', 'tipo2' => 'Arranque planta', 'tipo1' => 'Programada'])]);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->sole()->equipment_id)
        ->toBe($this->turbina->id);
});

it('records a plant-wide stoppage when the equipment column holds the reason', function (): void {
    // «Mantenimiento programado» y «Comedor» no son máquinas. Colgarlos de un
    // equipo le inventaría averías a su MTBF.
    importar([
        fila(['equipo' => 'Mantenimiento programado', 'tipo2' => 'Mantenimiento programado', 'tipo1' => 'Programada']),
        fila(['equipo' => 'Comedor', 'tipo2' => 'Capacitaciones', 'tipo1' => 'Externa', 'inicio' => '14:00']),
    ]);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->whereNull('equipment_id')->count())->toBe(2);
});

it('reports an unknown equipment instead of guessing one', function (): void {
    $result = importar([fila(['equipo' => 'Máquina Que No Existe'])]);

    expect($result['unmatched'])->toBe(['Máquina Que No Existe' => 1])
        ->and(EquipmentDowntimeEvent::withoutGlobalScopes()->sole()->equipment_id)->toBeNull();
});

// ── Correr dos veces no duplica ──────────────────────────────────────────────

it('skips stoppages that are already loaded', function (): void {
    // La planta reexporta el Excel completo cada mes; el importador tiene que
    // poder correr encima de lo que ya está sin duplicar horas perdidas.
    $rows = [fila(), fila(['inicio' => '14:00'])];

    importar($rows);
    $segunda = importar($rows);

    expect($segunda['imported'])->toBe(0)
        ->and($segunda['skipped'])->toBe(2)
        ->and(EquipmentDowntimeEvent::withoutGlobalScopes()->count())->toBe(2);
});

it('leaves the months after the cutoff alone', function (): void {
    $result = importar(
        [fila(), fila(['fecha' => '2026-08-04'])],
        until: Carbon::parse('2026-07-31')->endOfDay(),
    );

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(1);
});

it('writes nothing on a dry run', function (): void {
    $result = importar([fila()], dryRun: true);

    expect($result['imported'])->toBe(1)
        ->and(EquipmentDowntimeEvent::withoutGlobalScopes()->count())->toBe(0);
});

// ── Lo que no entiende, lo dice ──────────────────────────────────────────────

it('refuses a Tipo II that is not in the catalogue', function (): void {
    // Antes que inventar una categoría, el importador señala la fila: un Tipo II
    // nuevo en la planilla es una decisión de la planta, no del importador.
    $result = importar([fila(['tipo2' => 'Falla cósmica'])]);

    expect($result['imported'])->toBe(0)
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0])->toContain('Falla cósmica');
});

it('ignores the letterhead rows above the table', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'paros').'.csv';
    $fh = fopen($path, 'w');
    fputcsv($fh, ['INDICADORES DE MANTENIMIENTO', '', ''], escape: '');
    fputcsv($fh, ['Código:', 'FR-MTO-01'], escape: '');
    fputcsv($fh, ['Fecha', 'Día', 'Mes', 'Hora inicio'], escape: '');
    fputcsv($fh, fila(), escape: '');
    fclose($fh);

    $result = $this->importer->import($path, $this->plant, $this->actor);

    expect($result['rows'])->toBe(1)
        ->and($result['imported'])->toBe(1);
});

it('skips a row with no date or no duration', function (): void {
    $result = importar([
        fila(['horas' => '']),
        fila(['inicio' => '', 'fecha' => '2026-03-05']),
    ]);

    expect($result['imported'])->toBe(0)
        ->and($result['skipped'])->toBe(2)
        ->and($result['errors'])->toBe([]);
});

// ── El comando ───────────────────────────────────────────────────────────────

it('finds the organisation by its slug', function (): void {
    // `--tenant=ELPAJUIL` reventaba con un error de SQL: se comparaba el texto
    // contra la columna uuid `id`, y Postgres no lo permite. La prueba existe
    // porque el comando es la única puerta de entrada que usa la planta.
    $this->tenant->update(['slug' => 'ELPAJUIL']);
    $this->actor->tenants()->syncWithoutDetaching([
        $this->tenant->id => ['joined_at' => now(), 'is_primary_tenant' => true],
    ]);

    $this->artisan('downtime:import', [
        'file' => planilla([fila()]),
        '--tenant' => 'ELPAJUIL',
        '--dry-run' => true,
    ])->assertSuccessful();
});

it('says which organisation it cannot find instead of failing with SQL', function (): void {
    $this->artisan('downtime:import', [
        'file' => planilla([fila()]),
        '--tenant' => 'NO-EXISTE',
    ])->expectsOutputToContain('NO-EXISTE')->assertFailed();
});

it('actually writes the stoppages the command is pointed at', function (): void {
    $this->tenant->update(['slug' => 'ELPAJUIL']);
    $this->actor->tenants()->syncWithoutDetaching([
        $this->tenant->id => ['joined_at' => now(), 'is_primary_tenant' => true],
    ]);

    $this->artisan('downtime:import', [
        'file' => planilla([fila(), fila(['inicio' => '14:00'])]),
        '--tenant' => 'ELPAJUIL',
        '--until' => '2026-07-31',
    ])->assertSuccessful();

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->count())->toBe(2);
});
