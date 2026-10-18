<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga a la plataforma la entrada y la salida de cada día del libro de horas extras
 * (TH-NOM-P-001), para los días que la portería no registró.
 *
 * La portería manda: el libro solo llena huecos. Un día que ya tiene marcas del carné no
 * se toca, y tampoco uno ya confirmado. Los días que todavía no terminan —el libro trae
 * turnos programados— no entran: se pagarían horas que no han pasado.
 *
 * Cada día que entra queda como dos marcas manuales —entrada y salida— con la nota «Del
 * libro TH-NOM-P-001», y el día se arma con las mismas reglas del reloj. Volver a subir el
 * mismo libro no duplica nada: esos días ya tienen marcas.
 *
 * Con «reemplazar», el libro manda en los días que trae: las marcas de esos días de cada
 * trabajador del libro se anulan —no se borran: quedan a la vista con el motivo— y sus
 * días, aun confirmados, se rehacen con el libro. Lo de antes y lo de después de esos días
 * no se toca. Una nómina cerrada sobre esos días lo impide.
 */
class OvertimeWorkbookImporter
{
    public const NOTE = 'Del libro TH-NOM-P-001';

    public const REPLACED_REASON = 'Reemplazada por el libro TH-NOM-P-001';

    /** Marcas a menos de este margen del turno del libro se toman como el mismo turno. */
    private const OVERLAP_HOURS = 3;

    /** Un turno más largo que esto es, casi siempre, un a. m. por p. m. en el libro. */
    private const LONGEST_SHIFT_HOURS = 16;

    public const STATUS_LABELS = [
        'import' => 'Entra',
        'has_marks' => 'Ya tiene marcas de la portería',
        'confirmed' => 'Día ya confirmado',
        'future' => 'Todavía no termina',
        'unknown_employee' => 'Trabajador no encontrado (ni por cédula con su nombre ni por nombre)',
    ];

    public function __construct(
        private readonly OvertimeWorkbookReader $reader,
        private readonly AttendanceDayBuilder $builder,
    ) {}

    /**
     * Qué pasaría con cada día del libro, sin escribir nada.
     *
     * `issues` son los errores del libro que talento humano debe mirar antes de importar:
     * los que no dejó leer el libro y los que salen al cruzarlo con las fichas.
     *
     * @return array{rows: list<array{employee: ?Employee, name: string, date: CarbonImmutable, entry: CarbonImmutable, exit: CarbonImmutable, scheduledEnd: ?CarbonImmutable, status: string}>, counts: array<string, int>, missingWorkers: list<string>, window: ?array{0: CarbonImmutable, 1: CarbonImmutable}, toVoid: int, issues: list<string>}
     */
    public function plan(string $path, Tenant $tenant, bool $replace = false): array
    {
        $timezone = $tenant->plantTimezone();
        $now = CarbonImmutable::now($timezone);
        $employees = Employee::query()->forTenant($tenant->id)->get();
        $rows = [];
        $missing = [];
        ['sheets' => $sheets, 'issues' => $issues] = $this->reader->readWithIssues($path);
        $sheetOf = [];

        foreach ($sheets as $sheet) {
            $employee = self::match($employees, $sheet['document'], $sheet['name']);

            if (! $employee) {
                $missing[] = trim($sheet['name']) !== '' ? trim($sheet['name']) : $sheet['sheet'];
            } else {
                array_push($issues, ...$this->employeeIssues($sheet, $employee, $sheetOf));
            }

            foreach ($sheet['days'] as $day) {
                if (! $day['start'] || ! $day['exit']) {
                    continue;
                }

                $entry = CarbonImmutable::parse($day['date']->toDateString().' '.$day['start'], $timezone);
                $exit = CarbonImmutable::parse($day['date']->toDateString().' '.$day['exit'], $timezone);

                // «12:00pm» después de una entrada por la tarde es la medianoche que el libro
                // anota como mediodía, como lo lee también el paralelo del libro.
                if ($exit->hour === 12 && $exit->minute === 0 && $entry->hour >= 13) {
                    $exit = $exit->subHours(12);
                }

                // La salida igual o anterior a la entrada es del día siguiente: turno de noche.
                if ($exit->lte($entry)) {
                    $exit = $exit->addDay();
                }

                $scheduledEnd = self::scheduledEnd($day['scheduledEnd'] ?? null, $entry, $exit, $timezone);
                $status = $this->statusOf($employee, $entry, $exit, $now, $replace);
                array_push($issues, ...self::dayIssues($sheet['sheet'], $day, $entry, $exit, $scheduledEnd, $timezone));

                // El mismo turno escrito dos veces en el libro entra una sola vez.
                if ($status === 'import' && collect($rows)->contains(fn (array $row): bool => $row['status'] === 'import'
                    && $row['employee']?->is($employee)
                    && $row['entry']->lt($exit) && $row['exit']->gt($entry))) {
                    $status = 'has_marks';
                }

                $rows[] = [
                    'employee' => $employee,
                    'name' => $employee?->fullName() ?? trim($sheet['name']),
                    'date' => $day['date'],
                    'entry' => $entry,
                    'exit' => $exit,
                    'scheduledEnd' => $scheduledEnd,
                    'status' => $status,
                ];
            }
        }

        $counts = array_fill_keys(array_keys(self::STATUS_LABELS), 0);

        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }

        $window = $this->window($rows);
        $toVoid = $replace && $window
            ? collect($rows)->pluck('employee')->filter()->unique('id')->sum(fn (Employee $e): int => $this->scansToReplace($e, $window, $timezone)->count())
            : 0;

        return [
            'rows' => $rows,
            'counts' => $counts,
            'missingWorkers' => array_values(array_unique($missing)),
            'window' => $window,
            'toVoid' => $toVoid,
            'issues' => $issues,
        ];
    }

    /**
     * Lo que no cuadra entre la hoja y la ficha del trabajador que se le encontró.
     *
     * @param  array{sheet: string, document: string}  $sheet
     * @param  array<string, string>  $sheetOf  la hoja de cada trabajador ya visto
     * @return list<string>
     */
    private function employeeIssues(array $sheet, Employee $employee, array &$sheetOf): array
    {
        $issues = [];
        $document = self::digits($sheet['document']);

        if ($document === '') {
            $issues[] = "Hoja {$sheet['sheet']}: no tiene cédula; se reconoció a {$employee->fullName()} por el nombre.";
        } elseif ($document !== self::digits($employee->document_number)) {
            $issues[] = "Hoja {$sheet['sheet']}: la cédula {$document} no es la de la ficha de {$employee->fullName()} ({$employee->document_number}).";
        }

        if (isset($sheetOf[$employee->id])) {
            $issues[] = "Hoja {$sheet['sheet']}: es del mismo trabajador que la hoja {$sheetOf[$employee->id]} ({$employee->fullName()}).";
        }

        $sheetOf[$employee->id] ??= $sheet['sheet'];

        return $issues;
    }

    /**
     * Lo raro de un día con entrada y salida: un turno que no puede ser de una persona, o
     * un fin de horario que hubo que corregir o descartar.
     *
     * @param  array{row?: int, date: CarbonImmutable, scheduledEnd?: ?string}  $day
     * @return list<string>
     */
    private static function dayIssues(string $sheet, array $day, CarbonImmutable $entry, CarbonImmutable $exit, ?CarbonImmutable $scheduledEnd, string $timezone): array
    {
        $where = "Hoja {$sheet}".(isset($day['row']) ? ", fila {$day['row']}" : '').", {$day['date']->format('d/m/Y')}";
        $issues = [];
        $hours = $entry->diffInMinutes($exit) / 60;

        if ($hours > self::LONGEST_SHIFT_HOURS) {
            $issues[] = sprintf('%s: turno de %s horas (%s a %s); revise a. m. y p. m.', $where, round($hours, 1), $entry->format('g:i a'), $exit->format('g:i a'));
        }

        $written = $day['scheduledEnd'] ?? null;

        if (blank($written)) {
            return $issues;
        }

        $asWritten = CarbonImmutable::parse($entry->toDateString().' '.$written, $timezone);

        // «12:00pm» en un turno de tarde es la medianoche, como en la salida: no es un error.
        if ($asWritten->hour === 12 && $asWritten->minute === 0 && $entry->hour >= 13) {
            $asWritten = $asWritten->subHours(12);
        }

        $asWritten = $asWritten->lte($entry) ? $asWritten->addDay() : $asWritten;

        if ($scheduledEnd === null) {
            $issues[] = "{$where}: el fin horario {$asWritten->format('g:i a')} no cae dentro del turno; ese día se cuenta con la jornada.";
        } elseif (! $scheduledEnd->eq($asWritten)) {
            $issues[] = "{$where}: el fin horario {$asWritten->format('g:i a')} no cuadraba con el turno; se tomó como {$scheduledEnd->format('g:i a')}.";
        }

        return $issues;
    }

    /**
     * Carga los días que entran: dos marcas manuales por día y el día armado con ellas.
     *
     * @return array{imported: int, workers: int, voided: int, counts: array<string, int>, missingWorkers: list<string>, issues: list<string>}
     */
    public function import(string $path, Tenant $tenant, User $by, bool $replace = false): array
    {
        $plan = $this->plan($path, $tenant, $replace);
        $toImport = collect($plan['rows'])->where('status', 'import');
        $timezone = $tenant->plantTimezone();
        $window = $plan['window'];
        $workers = collect($plan['rows'])->pluck('employee')->filter()->unique('id')->values();

        if ($replace && $window) {
            $this->ensureNoClosedPayroll($tenant, $window);
        }

        $voided = 0;

        DB::transaction(function () use ($toImport, $by, $replace, $window, $workers, $timezone, &$voided): void {
            // Lo de antes se anula y sus días se quitan: el libro los rehace.
            if ($replace && $window) {
                foreach ($workers as $employee) {
                    foreach ($this->scansToReplace($employee, $window, $timezone) as $scan) {
                        $scan->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => self::REPLACED_REASON]);
                        $voided++;
                    }

                    AttendanceDay::query()
                        ->forTenant($employee->tenant_id)
                        ->where('employee_id', $employee->id)
                        ->between($window[0]->toDateString(), $window[1]->toDateString())
                        ->delete();
                }
            }

            foreach ($toImport as $row) {
                foreach ([[AttendanceDirection::Entrada, $row['entry']], [AttendanceDirection::Salida, $row['exit']]] as [$direction, $at]) {
                    AttendanceScan::create([
                        'tenant_id' => $row['employee']->tenant_id,
                        'employee_id' => $row['employee']->id,
                        'scanned_at' => $at->utc(),
                        // El fin del horario del libro va en la entrada: lo ordinario llega hasta ahí.
                        'scheduled_end_at' => $direction === AttendanceDirection::Entrada ? $row['scheduledEnd']?->utc() : null,
                        'direction' => $direction,
                        'source' => 'manual',
                        'recorded_by' => $by->id,
                        'notes' => self::NOTE,
                    ]);
                }
            }
        });

        // Un armado por trabajador, de su primer a su último día cargado, más uno: la salida
        // del turno de noche del último día cae al día siguiente.
        foreach ($toImport->groupBy(fn (array $row): string => $row['employee']->id) as $rows) {
            $this->builder->buildForEmployee(
                $rows->first()['employee'],
                $rows->min(fn (array $row): CarbonImmutable => $row['date']),
                $rows->max(fn (array $row): CarbonImmutable => $row['date'])->addDay(),
            );
        }

        return [
            'imported' => $toImport->count(),
            'workers' => $toImport->pluck('employee.id')->unique()->count(),
            'voided' => $voided,
            'counts' => $plan['counts'],
            'missingWorkers' => $plan['missingWorkers'],
            'issues' => $plan['issues'],
        ];
    }

    /**
     * El «FIN HORARIO LABORAL» del libro como instante, o null si no sirve.
     *
     * Va entre la entrada y la salida: después de la entrada y, a lo sumo, en la salida
     * (el turno de noche que el libro anota con el fin igual a la salida no tiene extras).
     * El libro se llena a mano y trae errores de a. m. y p. m. —«2:00am» por «2:00pm» en
     * un turno de 7 a 6—: si el fin no cae en el turno se prueba al revés, y si tampoco,
     * se descarta y el día se cuenta con la jornada.
     */
    public static function scheduledEnd(?string $time, CarbonImmutable $entry, CarbonImmutable $exit, string $timezone): ?CarbonImmutable
    {
        if (blank($time)) {
            return null;
        }

        $base = CarbonImmutable::parse($entry->toDateString().' '.$time, $timezone);

        foreach ([$base, $base->addHours(12), $base->subHours(12)] as $candidate) {
            // Antes de la entrada, es del día siguiente (turno de noche).
            if ($candidate->lte($entry)) {
                $candidate = $candidate->addDay();
            }

            // Un horario de menos de 4 horas no es un horario: es un error de a. m. y p. m.
            // que no se pudo corregir. Salvo que termine justo con la salida.
            $plausible = $candidate->eq($exit) || $entry->diffInMinutes($candidate) >= 240;

            if ($candidate->gt($entry) && $candidate->lte($exit) && $plausible) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Los días que trae el libro: del primero al último con entrada y salida.
     *
     * @param  list<array{date: CarbonImmutable, status: string}>  $rows
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function window(array $rows): ?array
    {
        // Solo los días que entran o chocan con lo que hay; los que todavía no terminan no.
        $dates = collect($rows)->whereNotIn('status', ['unknown_employee', 'future'])->pluck('date');

        return $dates->isEmpty() ? null : [$dates->min()->startOfDay(), $dates->max()->startOfDay()];
    }

    /**
     * Las marcas vigentes del trabajador en los días del libro. El turno de noche del último
     * día sale al siguiente: esa salida también es del libro. El que entró la noche antes
     * del primer día y sale ese día no lo es.
     *
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $window
     * @return Collection<int, AttendanceScan>
     */
    private function scansToReplace(Employee $employee, array $window, string $timezone): Collection
    {
        [$from, $to] = $window;
        $start = CarbonImmutable::parse($from->toDateString(), $timezone);
        $end = CarbonImmutable::parse($to->toDateString(), $timezone)->endOfDay();

        $scans = AttendanceScan::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [$start->subHours(AttendanceService::MAX_OPEN_SHIFT_HOURS)->utc(), $end->addHours(AttendanceService::MAX_OPEN_SHIFT_HOURS)->utc()])
            ->orderBy('scanned_at')
            ->get();

        $result = collect();
        $previous = null;

        foreach ($scans as $scan) {
            $at = CarbonImmutable::instance($scan->scanned_at)->setTimezone($timezone);
            $inside = $at->gte($start) && $at->lte($end);
            $closesInside = $scan->direction === AttendanceDirection::Salida && $previous?->direction === AttendanceDirection::Entrada;
            $previousAt = $previous ? CarbonImmutable::instance($previous->scanned_at)->setTimezone($timezone) : null;

            if ($inside && ! ($closesInside && $previousAt?->lt($start))) {
                $result->push($scan);
            } elseif ($at->gt($end) && $closesInside && $previousAt?->gte($start) && $previousAt->lte($end)) {
                $result->push($scan);
            }

            $previous = $scan;
        }

        return $result;
    }

    /** @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $window */
    private function ensureNoClosedPayroll(Tenant $tenant, array $window): void
    {
        $closed = PayrollRun::query()
            ->forTenant($tenant->id)
            ->where('status', PayrollRunStatus::Cerrada)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->whereNotNull('hours_from')->whereDate('hours_from', '<=', $window[1]->toDateString())->whereDate('hours_to', '>=', $window[0]->toDateString()))
                ->orWhere(fn ($q) => $q->whereNull('hours_from')->whereDate('period_start', '<=', $window[1]->toDateString())->whereDate('period_end', '>=', $window[0]->toDateString())))
            ->first();

        if ($closed) {
            throw new \RuntimeException("La nómina «{$closed->name}» ya está cerrada sobre esos días: reábrala antes de reemplazar las horas.");
        }
    }

    private function statusOf(?Employee $employee, CarbonImmutable $entry, CarbonImmutable $exit, CarbonImmutable $now, bool $replace = false): string
    {
        if (! $employee) {
            return 'unknown_employee';
        }

        if ($exit->gt($now)) {
            return 'future';
        }

        // Reemplazando, el libro manda: lo que hay en esos días se anula.
        if ($replace) {
            return 'import';
        }

        $hasMarks = AttendanceScan::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [$entry->subHours(self::OVERLAP_HOURS)->utc(), $exit->addHours(self::OVERLAP_HOURS)->utc()])
            ->exists();

        if ($hasMarks) {
            return 'has_marks';
        }

        $confirmed = AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $entry->toDateString())
            ->where('status', AttendanceDayStatus::Confirmada)
            ->exists();

        return $confirmed ? 'confirmed' : 'import';
    }

    /**
     * El trabajador de una hoja. La cédula sola no basta: en el libro hay hojas copiadas
     * que conservan la cédula de otra persona —la misma en cuatro hojas—, y cargar por ella
     * le pondría a uno las horas de otros. Se acepta la cédula solo si el nombre de la hoja
     * coincide con el del trabajador; si no, se busca por el nombre, y solo si hay uno.
     *
     * @param  Collection<int, Employee>  $employees
     */
    public static function match($employees, ?string $document, ?string $name): ?Employee
    {
        $digits = self::digits($document);
        $byDocument = $digits === '' ? null : $employees->first(fn (Employee $e): bool => self::digits($e->document_number) === $digits);

        if ($byDocument && self::sameName($name, $byDocument->fullName())) {
            return $byDocument;
        }

        $byName = $employees->filter(fn (Employee $e): bool => self::sameName($name, $e->fullName()));

        return $byName->count() === 1 ? $byName->first() : null;
    }

    /**
     * ¿El nombre escrito en la hoja es el de este trabajador? Basta con que dos de sus
     * palabras —o la única, si es una— estén en el nombre completo, sin tildes: la hoja
     * dice «ERNESTO RINCÓN» y el trabajador es «Ernesto Rincón Vargas».
     */
    private static function sameName(?string $sheetName, string $fullName): bool
    {
        $words = fn (?string $text): array => array_values(array_filter(
            preg_split('/\s+/', Str::upper(Str::ascii((string) $text))) ?: [],
            fn (string $word): bool => mb_strlen($word) >= 3,
        ));

        $sheet = $words($sheetName);
        $employee = $words($fullName);

        if ($sheet === []) {
            return false;
        }

        return count(array_intersect($sheet, $employee)) >= min(2, count($sheet));
    }

    private static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
