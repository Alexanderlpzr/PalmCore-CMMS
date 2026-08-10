<?php

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageReason;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga la planilla «REGISTROS DE PAROS» de la planta en el módulo de paros.
 *
 * El Excel se lleva a mano desde antes de que existiera el sistema y se sigue
 * llevando: por eso el importador lee la exportación CSV de esa hoja tal cual,
 * con sus mismos encabezados, en vez de pedir un formato nuevo. La planta
 * exporta y vuelve a importar cuando quiera; no se le cambia el procedimiento.
 *
 * Tres decisiones que explican todo lo demás:
 *
 * 1. **La duración manda sobre la hora de fin.** La columna «Tiempo paro (horas)»
 *    cuadra en las 605 filas del histórico; la de «Hora fin» no, porque Excel
 *    guarda las 24:00 como una fecha de 1900 y en alguna fila arrastró un día
 *    equivocado. `fin = inicio + horas` sale siempre bien y no necesita casos
 *    especiales.
 *
 * 2. **El Tipo I se respeta aunque contradiga la causa física.** La planta
 *    clasifica la misma «falla mecánica» como Operativa unas veces y como
 *    Mantenimiento otras, según quién asumió el paro. Esa contradicción es el
 *    dato que mide `failureAttributionGap()`; normalizarla la borraría.
 *
 * 3. **La columna «Equipo» no siempre trae un equipo.** A veces trae el motivo
 *    («Mantenimiento programado»), el sitio («Comedor») o el punto del proceso
 *    donde faltó fruta. Esas filas entran como paro de planta: colgarlas de una
 *    máquina que no falló le inventaría averías a su MTBF.
 */
class DowntimeLogImporter
{
    /**
     * Nombres del Excel que sí son equipos pero están escritos distinto que en el
     * inventario. Sin esto, «Turbina» —156 de las 605 filas— quedaría huérfana.
     *
     * @var array<string, string>
     */
    private const EQUIPMENT_ALIASES = [
        'turbina' => 'A10SPG.26.03',                             // Turbina Shinko RB-4 950 KVA
        'elevador de nuez' => 'A08KRS.02.01',                    // Elevador de Nueces
        'redler de fruto esterilizado' => 'A02STR.10.01',        // Transportador Redler de Fruto Esterilizado
        'redler 1' => 'A01REC.03.01',                            // Redler #1 Fruta de las Tolvas
        'tolva para fruto esterilizado dosificador' => 'A02STR.04.01',
        'dosificador de fruto cosido' => 'A02STR.04.01',         // Tolva y Dosificador para Fruto Esterilizado
        'filtro cepillo' => 'A06CLA.34.02',                      // Motor Filtro Cepillo
        'tableros ccm' => 'A10SPG.26.04',                        // Tablero CCM
        'bomba de saturacion' => 'A10SPG.28.01',                 // Bomba de Saturación de Agua del Distribuidor de Vapor
        'tanque de aceite recuperado de florentino' => 'A06CLA.30.02',
        'desarenador 1' => 'A06CLA.07.01',                       // Desarenador #1
        'tanque de lodos desarenados' => 'A06CLA.08.02',
        'bomba de lodos del preclarificador' => 'A06CLA.03.03',
    ];

    /**
     * Lo que la planta escribe en «Equipo» cuando no hay equipo. Son paros de
     * planta: o no se rompió nada (falta de fruta, tanques llenos, capacitación)
     * o el paro fue de toda la línea (mantenimiento programado, aseo).
     *
     * @var list<string>
     */
    private const NOT_EQUIPMENT = [
        'mantenimiento programado',
        'mantenimiento aseo',
        'comedor',
        'tolva para recepcion de fruto fresco',
        'tanque almacenamiento no 1',
        'tanque almacenamiento no 2',
    ];

    /** @var array<string, string> Tipo II de la planilla → {@see StoppageReason} */
    private const REASONS = [
        'mantenimiento programado' => 'mantenimiento_programado',
        'arranque planta' => 'arranque_de_planta',
        'arranque de planta' => 'arranque_de_planta',
        'apagado planta' => 'apagado_de_planta',
        'apagado de planta' => 'apagado_de_planta',
        'falla mecanica' => 'falla_mecanica',
        'falla electrica' => 'falla_electrica',
        'falla operativa' => 'falla_operativa',
        'atascamiento' => 'atascamiento',
        'falta de fruta esterilizada' => 'falta_fruta_esterilizada',
        'falta de fruta fresca' => 'falta_fruta_fresca',
        'falta de fruta' => 'falta_fruta_fresca',
        'corte de energia' => 'corte_energia_red',
        'corte de energia red' => 'corte_energia_red',
        'capacitaciones' => 'capacitaciones',
        'capacitacion' => 'capacitaciones',
    ];

    /** @var array<string, string> Sección de la planilla → {@see PlantSection} */
    private const SECTIONS = [
        'recepcion fruta' => 'recepcion_fruta',
        'recepcion de fruta' => 'recepcion_fruta',
        'esterilizacion' => 'esterilizacion',
        'desfrutado' => 'desfrutado',
        'desfibrado' => 'desfibrado',
        'raquis' => 'raquis',
        'extraccion' => 'extraccion',
        'clarificacion' => 'clarificacion',
        'palmisteria' => 'palmisteria',
        'generacion de vapor' => 'generacion_de_vapor',
        'generacion vapor' => 'generacion_de_vapor',
        'generacion electrica' => 'generacion_electrica',
        'planta general' => 'planta_general',
    ];

    /** Encabezados de la hoja, en el orden en que los exporta el Excel. */
    private const COLUMNS = [
        'fecha', 'dia', 'mes', 'hora_inicio', 'hora_fin', 'horas',
        'tipo_i', 'tipo_ii', 'seccion', 'equipo', 'causa', 'responsable',
    ];

    public function __construct(private readonly DowntimeService $downtime) {}

    /**
     * @param  string  $path  CSV exportado de la hoja «Registrio Paros»
     * @param  Carbon|null  $until  Ignora los paros posteriores a esta fecha
     * @return array{imported: int, skipped: int, rows: int, errors: list<string>, unmatched: array<string, int>}
     */
    public function import(
        string $path,
        Plant $plant,
        User $registeredBy,
        ?Carbon $until = null,
        bool $dryRun = false,
    ): array {
        $rows = $this->readCsv($path);

        $equipment = Equipment::withoutGlobalScopes()
            ->where('tenant_id', $plant->tenant_id)
            ->get()
            ->keyBy(fn (Equipment $e): string => $this->key($e->name));

        $byCode = Equipment::withoutGlobalScopes()
            ->where('tenant_id', $plant->tenant_id)
            ->pluck('id', 'code');

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $unmatched = [];
        $paros = [];

        foreach ($rows as $line => $row) {
            try {
                $paro = $this->normalizeRow($row, $equipment, $byCode, $unmatched);
            } catch (\Throwable $e) {
                $errors[] = "Fila {$line}: {$e->getMessage()}";

                continue;
            }

            if ($paro === null || ($until !== null && $paro['started_at']->gt($until))) {
                $skipped++;

                continue;
            }

            $paros[$line] = $paro;
        }

        // La planilla se llena a mano y no siempre en orden: dentro de un mismo día
        // aparece un paro de las 20:54 antes que uno de las 07:04. Cargarlos en
        // orden de reloj hace que dos paros que sí se cruzan en el origen se
        // rechacen siempre en el mismo sentido, y que reejecutar la carga dé el
        // mismo resultado en vez de depender del orden del archivo.
        uasort($paros, fn (array $a, array $b): int => $a['started_at'] <=> $b['started_at']);

        foreach ($paros as $line => $paro) {
            if ($this->alreadyImported($plant, $paro)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $imported++;

                continue;
            }

            // Cualquier fallo, no solo una regla de negocio: una fila con un dato
            // imposible no puede tumbar la carga de las otras seiscientas. Cada
            // paro va en su propia transacción y el error se reporta con su número
            // de fila para que se corrija en la planilla y se vuelva a correr.
            try {
                DB::transaction(fn () => $this->register($paro, $plant, $registeredBy));
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Fila {$line}: {$e->getMessage()}";
            }
        }

        return [
            'rows' => count($rows),
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * Registra el paro ya cerrado, con su hora de fin desde el principio.
     *
     * Importa que sea en un solo paso y no abrir-y-cerrar: un paro abierto se
     * considera vigente hasta el infinito, así que mientras lo estuviera chocaría
     * con cualquier paro posterior del mismo equipo. Al cargar diez meses de
     * histórico sobre una planta que ya tiene paros de este mes, eso rechaza todo
     * lo anterior a ellos —la turbina perdía sus ciento cincuenta y seis filas—
     * aunque ninguna se cruce de verdad con nada.
     *
     * @param  array<string, mixed>  $paro
     */
    private function register(array $paro, Plant $plant, User $registeredBy): void
    {
        $this->downtime->register([
            'tenant_id' => $plant->tenant_id,
            'plant_id' => $plant->id,
            'equipment_id' => $paro['equipment_id'],
            'section' => $paro['section'],
            'stoppage_reason' => $paro['reason'],
            'reported_type' => $paro['reported_type'],
            'stoppage_cause' => $paro['cause'],
            'started_at' => $paro['started_at'],
            'ended_at' => $paro['ended_at'],
            'affects_production' => true,
            'source' => 'import',
        ], $registeredBy);
    }

    /**
     * Un paro ya cargado no se vuelve a cargar: la planta reexporta el mismo Excel
     * mes a mes y el importador tiene que poder correr encima de lo que ya está.
     *
     * @param  array<string, mixed>  $paro
     */
    private function alreadyImported(Plant $plant, array $paro): bool
    {
        return EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('tenant_id', $plant->tenant_id)
            ->where('plant_id', $plant->id)
            ->where('started_at', $paro['started_at'])
            ->when(
                $paro['equipment_id'] !== null,
                fn ($q) => $q->where('equipment_id', $paro['equipment_id']),
                fn ($q) => $q->whereNull('equipment_id'),
            )
            ->exists();
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, Equipment>  $byName
     * @param  Collection<string, string>  $byCode
     * @param  array<string, int>  $unmatched
     * @return array<string, mixed>|null null cuando la fila no trae fecha ni horas
     */
    private function normalizeRow(array $row, $byName, $byCode, array &$unmatched): ?array
    {
        $date = trim($row['fecha'] ?? '');
        $time = trim($row['hora_inicio'] ?? '');
        $hours = (float) str_replace(',', '.', trim($row['horas'] ?? ''));

        if ($date === '' || $time === '' || $hours <= 0) {
            return null;
        }

        $startedAt = $this->parseStart($date, $time);
        $minutes = (int) round($hours * 60);

        $reason = self::REASONS[$this->key($row['tipo_ii'] ?? '')] ?? null;
        $section = self::SECTIONS[$this->key($row['seccion'] ?? '')] ?? null;

        // El Tipo I no necesita tabla: `ReportedStoppageType` está escrito con las
        // mismas palabras de la planilla («operativa», «programada»…) justamente
        // para no traducir el vocabulario de la planta.
        $reportedType = ReportedStoppageType::tryFrom($this->key($row['tipo_i'] ?? ''));

        if ($reason === null && trim($row['tipo_ii'] ?? '') !== '') {
            throw new \RuntimeException("Tipo II desconocido: «{$row['tipo_ii']}».");
        }

        if ($section === null && trim($row['seccion'] ?? '') !== '') {
            throw new \RuntimeException("Sección desconocida: «{$row['seccion']}».");
        }

        if ($reportedType === null && trim($row['tipo_i'] ?? '') !== '') {
            throw new \RuntimeException("Tipo I desconocido: «{$row['tipo_i']}».");
        }

        return [
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes($minutes),
            'equipment_id' => $this->resolveEquipment($row['equipo'] ?? '', $byName, $byCode, $unmatched),
            'section' => $section !== null ? PlantSection::from($section) : null,
            'reason' => $reason !== null ? StoppageReason::from($reason) : null,
            'reported_type' => $reportedType,
            'cause' => trim($row['causa'] ?? '') ?: null,
        ];
    }

    /**
     * @param  Collection<string, Equipment>  $byName
     * @param  Collection<string, string>  $byCode
     * @param  array<string, int>  $unmatched
     */
    private function resolveEquipment(string $raw, $byName, $byCode, array &$unmatched): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $key = $this->key($raw);

        if (in_array($key, self::NOT_EQUIPMENT, strict: true)) {
            return null;
        }

        if (isset(self::EQUIPMENT_ALIASES[$key])) {
            $code = self::EQUIPMENT_ALIASES[$key];

            if ($byCode->has($code)) {
                return $byCode->get($code);
            }
        }

        if ($byName->has($key)) {
            return $byName->get($key)->id;
        }

        // Ni equipo conocido ni motivo conocido: entra como paro de planta y se
        // reporta al final, para que alguien decida si falta en el inventario.
        $unmatched[$raw] = ($unmatched[$raw] ?? 0) + 1;

        return null;
    }

    /** Acepta `2025-10-07`, `07/10/2025` y las fechas con hora que exporta Excel. */
    private function parseStart(string $date, string $time): Carbon
    {
        $day = str_contains($date, '/')
            ? Carbon::createFromFormat('d/m/Y', explode(' ', $date)[0])
            : Carbon::parse(substr($date, 0, 10));

        [$h, $m] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        // Las 24:00 de la planilla son las 00:00 del día siguiente.
        return $day->startOfDay()->addHours($h)->addMinutes($m);
    }

    /** @return array<int, array<string, string>> indexado por número de fila del CSV */
    private function readCsv(string $path): array
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("No se pudo leer el archivo: {$path}");
        }

        $rows = [];
        $line = 0;

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $line++;

            // La hoja trae membrete y encabezados antes de los datos: la primera
            // fila útil es la que empieza con algo parseable como fecha.
            $first = trim((string) ($values[0] ?? ''));

            if ($first === '' || ! preg_match('/^\d{2,4}[-\/]\d{1,2}[-\/]\d{1,4}/', $first)) {
                continue;
            }

            $rows[$line] = array_combine(
                self::COLUMNS,
                array_map(
                    fn ($v): string => (string) $v,
                    array_slice(array_pad($values, count(self::COLUMNS), ''), 0, count(self::COLUMNS)),
                ),
            );
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Sin tildes, sin puntuación y en minúsculas: así se comparan los nombres que
     * la planta escribe a mano, donde «Generacion electrica » y «Generación
     * eléctrica» son lo mismo.
     *
     * Con `Str::ascii()` y no con `iconv('ASCII//TRANSLIT')`: iconv depende del
     * locale del sistema y en Windows convierte «Extracción» en «Extracci'on»,
     * así que el importador daría resultados distintos en el portátil y en el
     * servidor. La tabla de Laravel es la misma en todas partes.
     */
    private function key(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($value))) ?? '');
    }
}
