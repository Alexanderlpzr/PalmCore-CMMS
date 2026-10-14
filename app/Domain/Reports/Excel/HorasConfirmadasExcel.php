<?php

namespace App\Domain\Reports\Excel;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las horas confirmadas en Excel: un día por fila, con la entrada y la salida, las horas
 * trabajadas y cada recargo y extra por separado, y los totales al final. Las mismas filas
 * que muestra la pantalla, con su periodo y sus filtros.
 *
 * La entrada y la salida son las que se pagaron: las del ajuste si el día se ajustó, si
 * no la primera entrada y la última salida de la puerta.
 */
class HorasConfirmadasExcel
{
    /** Columna del día → encabezado, en el orden de la planilla. */
    private const HOURS = [
        'worked_hours' => 'Trabajadas',
        'ordinary_hours' => 'Ordinarias',
        'night_surcharge_hours' => 'Rec. nocturno',
        'sunday_surcharge_hours' => 'Rec. dominical',
        'night_sunday_surcharge_hours' => 'Rec. noct. dominical',
        'overtime_day_hours' => 'Extra diurna',
        'overtime_night_hours' => 'Extra nocturna',
        'overtime_sunday_day_hours' => 'Extra dom. diurna',
        'overtime_sunday_night_hours' => 'Extra dom. nocturna',
    ];

    public function __construct(private readonly AttendanceCorrectionService $corrections) {}

    /**
     * @param  Collection<int, AttendanceDay>  $days
     */
    public function download(Collection $days, ?string $periodLabel, string $filename): StreamedResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = Filament::getTenant();
        $timezone = $tenant?->plantTimezone() ?? 'America/Bogota';
        $days = $days->loadMissing(['employee', 'confirmedBy:id,name']);

        $headers = ['Fecha', 'Trabajador', 'Cédula', 'Cargo', 'Entrada', 'Salida', ...array_values(self::HOURS), 'Ajustado a mano', 'Firmado por'];
        $hourColumns = range(7, 6 + count(self::HOURS));
        $formats = array_fill_keys($hourColumns, FrondaWorkbook::HOURS);

        $rows = $days->map(function (AttendanceDay $day) use ($timezone): array {
            [$entry, $exit] = $this->entryAndExit($day, $timezone);

            return [
                $day->work_date->format('d/m/Y'),
                $day->employee?->fullName() ?? '—',
                $day->employee?->document_number,
                $day->employee?->position,
                $entry,
                $exit,
                ...array_map(fn (string $column): float => round((float) $day->{$column}, 2), array_keys(self::HOURS)),
                $day->isManuallyAdjusted() ? ($day->adjustment_reason ?: 'Sí') : '',
                $day->confirmedBy?->name,
            ];
        });

        $totals = ['Total ('.$days->count().' días)', '', '', '', '', ''];

        foreach (array_keys(self::HOURS) as $column) {
            $totals[] = round((float) $days->sum(fn (AttendanceDay $day): float => (float) $day->{$column}), 2);
        }

        return FrondaWorkbook::create()
            ->sheet('Horas confirmadas')
            ->widths([1 => 12, 2 => 32, 3 => 14, 4 => 24, 5 => 10, 6 => 10] + array_fill(7, count(self::HOURS), 12) + [7 + count(self::HOURS) => 30, 8 + count(self::HOURS) => 20])
            ->banner('Horas confirmadas', $periodLabel, $tenant?->name, count($headers))
            ->table($headers, $rows, $formats)
            ->totals([...$totals, '', ''], $formats)
            ->footer()
            ->download($filename);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function entryAndExit(AttendanceDay $day, string $timezone): array
    {
        // En 12 horas, como se lee en la planta: «06:00 pm», no «18:00».
        $time = fn ($moment): string => $moment ? $moment->copy()->setTimezone($timezone)->format('h:i a') : '';

        if ($day->adjusted_entry_at && $day->adjusted_exit_at) {
            return [$time($day->adjusted_entry_at), $time($day->adjusted_exit_at)];
        }

        $marks = $this->corrections->marksOfDay($day);

        return [
            $time($marks->first(fn (AttendanceScan $m): bool => $m->direction === AttendanceDirection::Entrada)?->scanned_at),
            $time($marks->last(fn (AttendanceScan $m): bool => $m->direction === AttendanceDirection::Salida)?->scanned_at),
        ];
    }
}
