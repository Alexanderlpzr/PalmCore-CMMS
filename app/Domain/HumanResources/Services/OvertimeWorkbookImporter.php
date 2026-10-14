<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
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
 */
class OvertimeWorkbookImporter
{
    public const NOTE = 'Del libro TH-NOM-P-001';

    /** Marcas a menos de este margen del turno del libro se toman como el mismo turno. */
    private const OVERLAP_HOURS = 3;

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
     * @return array{rows: list<array{employee: ?Employee, name: string, date: CarbonImmutable, entry: CarbonImmutable, exit: CarbonImmutable, status: string}>, counts: array<string, int>, missingWorkers: list<string>}
     */
    public function plan(string $path, Tenant $tenant): array
    {
        $timezone = $tenant->plantTimezone();
        $now = CarbonImmutable::now($timezone);
        $employees = Employee::query()->forTenant($tenant->id)->get();
        $rows = [];
        $missing = [];

        foreach ($this->reader->read($path) as $sheet) {
            $employee = self::match($employees, $sheet['document'], $sheet['name']);

            if (! $employee) {
                $missing[] = trim($sheet['name']) !== '' ? trim($sheet['name']) : $sheet['sheet'];
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

                $status = $this->statusOf($employee, $entry, $exit, $now);

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
                    'status' => $status,
                ];
            }
        }

        $counts = array_fill_keys(array_keys(self::STATUS_LABELS), 0);

        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }

        return ['rows' => $rows, 'counts' => $counts, 'missingWorkers' => array_values(array_unique($missing))];
    }

    /**
     * Carga los días que entran: dos marcas manuales por día y el día armado con ellas.
     *
     * @return array{imported: int, workers: int, counts: array<string, int>, missingWorkers: list<string>}
     */
    public function import(string $path, Tenant $tenant, User $by): array
    {
        $plan = $this->plan($path, $tenant);
        $toImport = collect($plan['rows'])->where('status', 'import');

        DB::transaction(function () use ($toImport, $by): void {
            foreach ($toImport as $row) {
                foreach ([[AttendanceDirection::Entrada, $row['entry']], [AttendanceDirection::Salida, $row['exit']]] as [$direction, $at]) {
                    AttendanceScan::create([
                        'tenant_id' => $row['employee']->tenant_id,
                        'employee_id' => $row['employee']->id,
                        'scanned_at' => $at->utc(),
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
            'counts' => $plan['counts'],
            'missingWorkers' => $plan['missingWorkers'],
        ];
    }

    private function statusOf(?Employee $employee, CarbonImmutable $entry, CarbonImmutable $exit, CarbonImmutable $now): string
    {
        if (! $employee) {
            return 'unknown_employee';
        }

        if ($exit->gt($now)) {
            return 'future';
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
