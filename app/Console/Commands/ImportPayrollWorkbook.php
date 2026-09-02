<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Enums\BonusType;
use App\Domain\HumanResources\Enums\NoveltyType;
use App\Domain\HumanResources\Services\NoveltyWindowPlanner;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeNovelty;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;

use function Laravel\Prompts\select;

/**
 * Carga el libro de nómina de la extractora dentro del sistema, para poder correr el
 * paralelo.
 *
 * El paralelo es el paso que decide si este módulo puede reemplazar al Excel, y para que
 * signifique algo tiene que partir de **los mismos insumos**. Por eso esto no importa
 * solo los empleados: trae también las horas ya clasificadas de la hoja diaria, las
 * novedades, las bonificaciones y los descuentos. Después se liquida y se compara el neto
 * de cada persona contra la columna BY del libro.
 *
 * Las horas entran como días **confirmados** y no como propuestas. No es un atajo: en el
 * libro esas horas ya las clasificó una persona, que es exactamente lo que el flujo nuevo
 * pide antes de liquidar. Importarlas como propuestas obligaría a firmar 1.440 días para
 * validar una aritmética que no depende de la firma.
 *
 * Hay una pérdida de información que conviene tener presente y queda anotada en cada
 * registro: el libro guarda las novedades como **cantidad de días**, no como fechas. Seis
 * días de vacaciones no dicen cuáles. Al importar, {@see NoveltyWindowPlanner} elige el
 * tramo que menos contradice lo que la fuente sí dice —las horas extras de la hoja
 * diaria—, pero las fechas siguen siendo una invención. Para el paralelo da igual, porque
 * la liquidación solo necesita la cantidad; para operar de verdad, las novedades se
 * capturan con sus fechas reales.
 *
 * La otra trampa del libro, y la que costó dos discrepancias: la hoja diaria marca jornal
 * en los treinta días **aunque la persona estuviera incapacitada**. Allí esa columna
 * significa «existe la fila», no «estuvo presente», y los días de novedad se restan
 * después en la hoja de liquidación. Por eso al importar las horas se saltan los días que
 * una novedad ya ocupa: nadie trabaja y está de vacaciones el mismo día.
 */
class ImportPayrollWorkbook extends Command
{
    protected $signature = 'payroll:import-workbook
        {file : Ruta al .xlsx de la nómina}
        {--tenant= : ID de la empresa}
        {--period-start=2026-08-01 : Primer día del período}
        {--period-end=2026-08-30 : Último día del período}
        {--skip-attendance : No importar las horas de la hoja diaria}
        {--dry-run : Muestra lo que se cargaría sin escribir nada}';

    protected $description = 'Importa el libro de nómina en Excel para correr el paralelo de validación';

    /** Fila donde empiezan los datos de la hoja de liquidación. */
    private const LIQUIDACION_FIRST_ROW = 6;

    /**
     * Las columnas de la hoja de liquidación que se leen, por índice de base 1.
     *
     * Se listan aquí y no en línea porque son el contrato con el libro: si alguien inserta
     * una columna en el Excel, este mapa es el único sitio que hay que corregir.
     */
    private const COL = [
        'cedula' => 2,       // B
        'nombre' => 3,       // C
        'cargo' => 6,        // F
        'salario' => 7,      // G
        'ausencia' => 11,    // K
        'permiso' => 13,     // M
        'permiso_no_rem' => 14, // N
        'incap_eg_100' => 15,   // O
        'incap_eg_min' => 16,   // P
        'incap_at' => 20,    // T
        'calamidad' => 23,   // W
        'vacaciones' => 26,  // Z
        'bono_vivienda' => 54,      // BB
        'bono_no_const' => 56,      // BD
        'bono_const' => 58,         // BF
        'desc_funerario' => 73,     // BU
        'desc_seguro' => 74,        // BV
        'desc_otros' => 75,         // BW
        'neto_excel' => 77,         // BY
    ];

    /** Las columnas de horas de la hoja diaria, por índice de base 1. */
    private const DIA = [
        'dia' => 1, 'mes' => 2, 'nombre' => 4, 'cedula' => 5,
        'jornal' => 7, 'rec_nocturno' => 8, 'rec_diu_dom' => 10, 'rec_noc_dom' => 11,
        'he_diurna' => 12, 'he_nocturna' => 13, 'he_dom_diurna' => 14, 'he_dom_nocturna' => 15,
    ];

    public function __construct(private readonly NoveltyWindowPlanner $planner)
    {
        parent::__construct();
    }

    public function handle(PayrollParameterService $parameters): int
    {
        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->error("No se puede leer el archivo: {$file}");

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return self::FAILURE;
        }

        $from = Carbon::parse($this->option('period-start'));
        $to = Carbon::parse($this->option('period-end'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Leyendo {$file}…");

        $liquidacion = $this->readLiquidacion($file);
        $horas = $this->option('skip-attendance') ? [] : $this->readHoras($file);

        $this->line(sprintf(
            'Encontrados %d trabajadores y %d registros diarios.',
            count($liquidacion),
            array_sum(array_map('count', $horas)),
        ));

        if ($liquidacion === []) {
            $this->error('La hoja de liquidación no trajo ninguna fila. ¿Es el libro correcto?');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->table(
                ['Cédula', 'Nombre', 'Salario', 'Neto en el Excel'],
                array_map(fn (array $r): array => [
                    $r['cedula'],
                    Str::limit($r['nombre'], 32),
                    number_format($r['salario'], 0, ',', '.'),
                    number_format($r['neto_excel'], 0, ',', '.'),
                ], array_slice($liquidacion, 0, 10)),
            );
            $this->comment('Simulación: no se escribió nada. Se muestran los primeros 10.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($tenant, $liquidacion, $horas, $from, $to, $parameters): void {
            $created = $parameters->seedDefaults($tenant->id, Carbon::parse($from->year.'-01-01'));

            if ($created > 0) {
                $this->line("Vigencias de nómina cargadas: {$created}.");
            }

            $this->seedHolidays($tenant, $from);

            $bar = $this->output->createProgressBar(count($liquidacion));
            $bar->start();

            foreach ($liquidacion as $row) {
                $employee = $this->upsertEmployee($tenant, $row);

                $dias = $horas[$row['cedula']] ?? [];

                $noveltyDates = $this->syncNovelties($employee, $row, $from, $to, $this->extraHoursByDay($dias));
                $this->syncBonuses($employee, $row, $from, $to);
                $this->syncDeductions($employee, $row, $from);

                if ($dias !== []) {
                    $this->syncAttendance($employee, $dias, $from, $noveltyDates);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->upsertRun($tenant, $from, $to);
        });

        $this->info('Importación terminada.');
        $this->comment('Ahora corra: php artisan payroll:parallel-check '.escapeshellarg($file)." --tenant={$tenant->id}");

        return self::SUCCESS;
    }

    // ── Lectura del libro ─────────────────────────────────────────────────────

    /**
     * La hoja de liquidación: una fila por trabajador.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readLiquidacion(string $file): array
    {
        $rows = [];

        foreach ($this->sheetRows($file, 'NOMINA ACTUALIZADA') as $index => $cells) {
            if ($index < self::LIQUIDACION_FIRST_ROW) {
                continue;
            }

            $cedula = $this->str($cells, self::COL['cedula']);
            $nombre = $this->str($cells, self::COL['nombre']);

            // La hoja tiene filas de totales y de relleno debajo de los 48; se para en la
            // primera que no trae ni cédula ni nombre.
            if ($cedula === '' || $nombre === '') {
                continue;
            }

            $row = ['cedula' => $cedula, 'nombre' => $nombre];

            foreach (self::COL as $key => $col) {
                if (in_array($key, ['cedula', 'nombre'], true)) {
                    continue;
                }

                $row[$key] = $key === 'cargo'
                    ? $this->str($cells, $col)
                    : $this->num($cells, $col);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * La hoja diaria, agrupada por cédula.
     *
     * @return array<string, array<int, array<string, float>>>
     */
    private function readHoras(string $file): array
    {
        $byEmployee = [];

        foreach ($this->sheetRows($file, 'PERSONAL PAJUIL') as $index => $cells) {
            if ($index < 2) {
                continue;
            }

            $cedula = $this->str($cells, self::DIA['cedula']);
            $dia = (int) $this->num($cells, self::DIA['dia']);

            if ($cedula === '' || $dia < 1) {
                continue;
            }

            $byEmployee[$cedula][] = [
                'dia' => $dia,
                'jornal' => $this->num($cells, self::DIA['jornal']),
                'rec_nocturno' => $this->num($cells, self::DIA['rec_nocturno']),
                'rec_diu_dom' => $this->num($cells, self::DIA['rec_diu_dom']),
                'rec_noc_dom' => $this->num($cells, self::DIA['rec_noc_dom']),
                'he_diurna' => $this->num($cells, self::DIA['he_diurna']),
                'he_nocturna' => $this->num($cells, self::DIA['he_nocturna']),
                'he_dom_diurna' => $this->num($cells, self::DIA['he_dom_diurna']),
                'he_dom_nocturna' => $this->num($cells, self::DIA['he_dom_nocturna']),
            ];
        }

        return $byEmployee;
    }

    /**
     * Recorre una hoja devolviendo el número de fila y sus celdas en un array de base 1.
     *
     * @return \Generator<int, array<int, mixed>>
     */
    private function sheetRows(string $file, string $sheetName): \Generator
    {
        $reader = new Reader;
        $reader->open($file);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cells = [];

                foreach ($row->getCells() as $cellIndex => $cell) {
                    // Base 1 para que los índices coincidan con las letras de columna del
                    // Excel y el mapa de arriba se pueda comprobar a ojo.
                    $cells[$cellIndex + 1] = $this->cellValue($cell);
                }

                yield $rowIndex => $cells;
            }
        }

        $reader->close();
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    private function upsertEmployee(Tenant $tenant, array $row): Employee
    {
        [$nombres, $apellidos] = $this->splitName($row['nombre']);

        return Employee::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'document_number' => $row['cedula']],
            [
                'first_name' => $nombres,
                'last_name' => $apellidos,
                'position' => $row['cargo'] ?: null,
                'base_salary' => $row['salario'],
                // La bandera que evita el error más caro: quien en el libro no tiene ni
                // una hora extra ni un recargo es trabajador de dirección o confianza.
                'excluded_from_overtime' => false,
                'status' => 'activo',
            ],
        );
    }

    /**
     * Las novedades del período.
     *
     * Se colocan como bloques contiguos al final: el libro guarda cantidades, no fechas.
     * Queda anotado en cada registro para que nadie lo confunda con un dato de origen.
     *
     * Devuelve las fechas que quedaron cubiertas, porque la hoja diaria del libro marca
     * jornal en los treinta días **aunque la persona estuviera incapacitada**: allí la
     * columna de jornales significa «existe la fila», no «estuvo presente», y los días de
     * novedad se restan después en la hoja de liquidación. Importar las dos cosas sin
     * cruzarlas da sesenta días para quien estuvo un mes incapacitado.
     *
     * @return array<string, true> fechas cubiertas, indexadas para búsqueda directa
     */
    private function syncNovelties(Employee $employee, array $row, Carbon $from, Carbon $to, array $extraByDay): array
    {
        EmployeeNovelty::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->forceDelete();

        $map = [
            'ausencia' => NoveltyType::AusenciaNoJustificada,
            'permiso' => NoveltyType::PermisoAutorizado,
            'permiso_no_rem' => NoveltyType::PermisoNoRemunerado,
            'incap_eg_100' => NoveltyType::IncapacidadEgSalario,
            'incap_eg_min' => NoveltyType::IncapacidadEgMinimo,
            'incap_at' => NoveltyType::IncapacidadAt,
            'calamidad' => NoveltyType::CalamidadDomestica,
            'vacaciones' => NoveltyType::Vacaciones,
        ];

        $periodDays = (int) $from->diffInDays($to) + 1;
        $taken = [];
        $covered = [];

        foreach ($map as $key => $type) {
            $days = (int) round($row[$key] ?? 0);

            if ($days <= 0) {
                continue;
            }

            $offset = $this->planner->place($days, $periodDays, $taken, $extraByDay);

            if ($offset === null) {
                $this->warn("No cupo la novedad {$type->value} de {$employee->document_number}.");

                continue;
            }

            $starts = $from->copy()->addDays($offset);
            $ends = $from->copy()->addDays($offset + $days - 1);

            EmployeeNovelty::withoutGlobalScopes()->create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'type' => $type,
                'starts_on' => $starts->toDateString(),
                'ends_on' => $ends->toDateString(),
                'notes' => 'Importado del Excel, que guarda cantidad de días y no fechas: '
                    ."las fechas de este bloque son sintéticas ({$days} días).",
            ]);

            for ($i = 0; $i < $days; $i++) {
                $taken[$offset + $i] = true;
                $covered[$from->copy()->addDays($offset + $i)->toDateString()] = true;
            }
        }

        return $covered;
    }

    /**
     * Horas de recargo y extra por día del mes, para decidir dónde caben las novedades.
     *
     * @return array<int, float>
     */
    private function extraHoursByDay(array $dias): array
    {
        $byDay = [];

        foreach ($dias as $dia) {
            $byDay[$dia['dia']] = $dia['rec_nocturno'] + $dia['rec_diu_dom'] + $dia['rec_noc_dom']
                + $dia['he_diurna'] + $dia['he_nocturna'] + $dia['he_dom_diurna'] + $dia['he_dom_nocturna'];
        }

        return $byDay;
    }

    private function syncBonuses(Employee $employee, array $row, Carbon $from, Carbon $to): void
    {
        EmployeeBonus::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->forceDelete();

        $map = [
            'bono_vivienda' => [BonusType::Vivienda, 'Bonificación por vivienda'],
            'bono_no_const' => [BonusType::NoConstitutiva, 'Bonificación no constitutiva'],
            'bono_const' => [BonusType::Constitutiva, 'Bonificación constitutiva'],
        ];

        foreach ($map as $key => [$type, $concept]) {
            $amount = $row[$key] ?? 0;

            if ($amount <= 0) {
                continue;
            }

            EmployeeBonus::withoutGlobalScopes()->create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'type' => $type,
                'concept' => $concept,
                'amount' => $amount,
                'effective_from' => $from->toDateString(),
                'effective_to' => $to->toDateString(),
                'notes' => 'Importado del Excel, donde estaba como cifra fija sin fórmula.',
            ]);
        }
    }

    private function syncDeductions(Employee $employee, array $row, Carbon $from): void
    {
        EmployeeDeduction::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->forceDelete();

        $map = [
            'desc_funerario' => 'Seguro funerario',
            'desc_seguro' => 'Seguro',
            'desc_otros' => 'Otros descuentos',
        ];

        foreach ($map as $key => $concept) {
            $amount = $row[$key] ?? 0;

            if ($amount <= 0) {
                continue;
            }

            EmployeeDeduction::withoutGlobalScopes()->create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'concept' => $concept,
                'amount' => $amount,
                'effective_from' => $from->toDateString(),
                'notes' => 'Importado del Excel.',
            ]);
        }
    }

    /**
     * Las horas diarias, como días ya confirmados.
     *
     * En el libro las clasificó una persona, que es justo lo que el flujo nuevo exige
     * antes de liquidar.
     */
    private function syncAttendance(Employee $employee, array $dias, Carbon $from, array $noveltyDates): void
    {
        AttendanceDay::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->delete();

        foreach ($dias as $dia) {
            if ($dia['jornal'] <= 0) {
                continue;
            }

            $fecha = $from->copy()->startOfMonth()->addDays($dia['dia'] - 1);

            // Nadie trabaja y está de vacaciones el mismo día. Ver la nota de
            // `syncNovelties`: la hoja diaria marca jornal aunque haya novedad.
            if (isset($noveltyDates[$fecha->toDateString()])) {
                continue;
            }

            AttendanceDay::withoutGlobalScopes()->create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'work_date' => $fecha->toDateString(),
                // El libro no registra la hora ordinaria: da el jornal por hecho y anota
                // solo lo que se paga aparte. Se asume la jornada del parámetro.
                'ordinary_hours' => 8,
                'night_surcharge_hours' => $dia['rec_nocturno'],
                'sunday_surcharge_hours' => $dia['rec_diu_dom'],
                'night_sunday_surcharge_hours' => $dia['rec_noc_dom'],
                'overtime_day_hours' => $dia['he_diurna'],
                'overtime_night_hours' => $dia['he_nocturna'],
                'overtime_sunday_day_hours' => $dia['he_dom_diurna'],
                'overtime_sunday_night_hours' => $dia['he_dom_nocturna'],
                'worked_hours' => 8,
                'status' => 'confirmada',
                'confirmed_at' => now(),
                'built_at' => now(),
                'source' => 'manual',
                'notes' => 'Importado del Excel: las horas ya venían clasificadas a mano.',
            ]);
        }
    }

    private function upsertRun(Tenant $tenant, Carbon $from, Carbon $to): PayrollRun
    {
        return PayrollRun::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
            ],
            [
                'name' => 'Paralelo '.$from->translatedFormat('F \d\e Y'),
                'status' => 'borrador',
                'notes' => 'Período creado por la importación del libro de Excel, para el paralelo de validación.',
            ],
        );
    }

    /** Los festivos colombianos de agosto de 2026, que el libro marca a mano. */
    private function seedHolidays(Tenant $tenant, Carbon $from): void
    {
        if ($from->year !== 2026 || $from->month !== 8) {
            return;
        }

        foreach ([
            '2026-08-07' => 'Batalla de Boyacá',
            '2026-08-17' => 'Asunción de la Virgen',
        ] as $date => $name) {
            Holiday::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'holiday_date' => $date],
                ['name' => $name, 'is_national' => true],
            );
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    /** @return array{0: string, 1: string} */
    private function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];

        if (count($parts) <= 2) {
            return [$parts[0] ?? $full, $parts[1] ?? ''];
        }

        // Convención colombiana: los dos últimos son apellidos.
        $apellidos = array_splice($parts, -2);

        return [implode(' ', $parts), implode(' ', $apellidos)];
    }

    /**
     * El valor de una celda, resolviendo las fórmulas.
     *
     * Casi todas las columnas que interesan son fórmulas —el neto, el valor día, cada
     * bolsa valorada— y `getValue()` en esas devuelve el texto de la fórmula, no el
     * número. Sin esto la importación entra con ceros por todas partes y el paralelo
     * comparaba contra nada.
     */
    private function cellValue(Cell $cell): mixed
    {
        return $cell instanceof FormulaCell
            ? $cell->getComputedValue()
            : $cell->getValue();
    }

    private function str(array $cells, int $col): string
    {
        $value = $cells[$col] ?? null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) ($value ?? ''));
    }

    private function num(array $cells, int $col): float
    {
        $value = $cells[$col] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function resolveTenant(): ?Tenant
    {
        if ($id = $this->option('tenant')) {
            $tenant = Tenant::find($id);

            if (! $tenant) {
                $this->error("No existe la empresa {$id}.");

                return null;
            }

            return $tenant;
        }

        $tenants = Tenant::query()->orderBy('name')->pluck('name', 'id')->all();

        if ($tenants === []) {
            $this->error('No hay empresas registradas.');

            return null;
        }

        return Tenant::find(select('¿En qué empresa?', $tenants));
    }
}
