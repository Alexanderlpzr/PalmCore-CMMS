<?php

namespace App\Filament\Pages;

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Enums\MeterReadingFrequency;
use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La matriz de horómetros al estilo del Excel de la planta: equipos en las filas,
 * fechas en las columnas, y en cada celda el horómetro leído más las horas
 * trabajadas del período. Dos hojas: la diaria (equipos críticos) y la semanal
 * (el resto). Esta clase abstracta es el motor; cada hoja es una subclase que
 * solo declara su frecuencia y su ventana.
 *
 * A diferencia del Excel, las horas se sacan del `delta` que ya calcula
 * EquipmentMeterReadingService —que nunca da negativo cuando cambian el dial—.
 * Solo se capturan celdas vacías (lecturas nuevas): corregir una lectura pasada
 * es otro problema (recalcular la serie) que aquí no se toca.
 */
abstract class MeterRegister extends Page
{
    /** Fecha más reciente de la ventana visible (Y-m-d). */
    public string $anchor = '';

    /** Valores tecleados pendientes de guardar: draft[equipmentId][dateKey]. */
    public array $draft = [];

    abstract protected function frequency(): MeterReadingFrequency;

    /** Cuántas columnas de fecha se ven a la vez. */
    abstract protected function columnCount(): int;

    /** 'day' o 'week': el paso entre columnas. */
    abstract protected function stepUnit(): string;

    public function mount(): void
    {
        $this->anchor = $this->alignAnchor(Carbon::today())->format('Y-m-d');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_super_admin || $user?->can('equipment-meter-readings.view'));
    }

    // ── Navegación de la ventana ──────────────────────────────────────────────

    public function previousWindow(): void
    {
        $this->anchor = $this->step(Carbon::parse($this->anchor), -$this->columnCount())->format('Y-m-d');
    }

    public function nextWindow(): void
    {
        $next = $this->step(Carbon::parse($this->anchor), $this->columnCount());
        $today = $this->alignAnchor(Carbon::today());

        // No dejar avanzar al futuro: la última columna es el período actual.
        $this->anchor = $next->greaterThan($today) ? $today->format('Y-m-d') : $next->format('Y-m-d');
    }

    public function goToToday(): void
    {
        $this->anchor = $this->alignAnchor(Carbon::today())->format('Y-m-d');
    }

    // ── Captura ───────────────────────────────────────────────────────────────

    public function saveCell(string $equipmentId, string $dateKey): void
    {
        $raw = $this->draft[$equipmentId][$dateKey] ?? null;

        if ($raw === null || $raw === '') {
            return;
        }

        $equipment = Equipment::query()->find($equipmentId);

        if ($equipment === null) {
            return;
        }

        try {
            app(EquipmentMeterReadingService::class)->record(
                equipment: $equipment,
                readingValue: (float) $raw,
                recordedBy: auth()->user(),
                unit: $equipment->meter_unit ?? MeterReadingUnit::Hours,
                // Mediodía de la fecha de la columna: evita que el huso horario
                // corra la lectura al día anterior.
                recordedAt: Carbon::parse($dateKey)->setTime(12, 0),
            );

            unset($this->draft[$equipmentId][$dateKey]);

            Notification::make()
                ->title("{$equipment->code}: {$raw} guardado")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo guardar la lectura')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Datos de la vista ─────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $columns = $this->columnDates();
        $equipment = $this->equipment();

        $readings = $this->readingsIn($equipment->pluck('id'), $columns);
        $matrix = $this->buildMatrix($equipment, $columns, $readings);

        return [
            'columns' => array_map(fn (Carbon $d): array => [
                'key' => $d->format('Y-m-d'),
                'label' => $this->stepUnit() === 'week'
                    ? $d->translatedFormat('d M')
                    : $d->translatedFormat('D d'),
            ], $columns),
            'rows' => $matrix['rows'],
            'columnTotals' => $matrix['columnTotals'],
            'grandTotal' => $matrix['grandTotal'],
            'rangeLabel' => reset($columns)->translatedFormat('d M Y').' — '.end($columns)->translatedFormat('d M Y'),
            'isDaily' => $this->stepUnit() === 'day',
            'canGoNext' => Carbon::parse($this->anchor)->lessThan($this->alignAnchor(Carbon::today())),
        ];
    }

    /**
     * @return list<Carbon> de la más vieja a la más reciente (izquierda → derecha)
     */
    private function columnDates(): array
    {
        $anchor = Carbon::parse($this->anchor);

        $dates = [];
        for ($i = $this->columnCount() - 1; $i >= 0; $i--) {
            $dates[] = $this->step($anchor->copy(), -$i);
        }

        return $dates;
    }

    /**
     * @return Collection<int, Equipment>
     */
    private function equipment(): Collection
    {
        return Equipment::query()
            ->where('reading_frequency', $this->frequency()->value)
            ->whereNotIn('status', [EquipmentStatus::Retired->value, EquipmentStatus::Disposed->value])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'current_meter_reading', 'meter_unit']);
    }

    /**
     * @param  Collection<int, string>  $equipmentIds
     * @param  list<Carbon>  $columns
     * @return Collection<int, EquipmentMeterReading>
     */
    private function readingsIn(Collection $equipmentIds, array $columns): Collection
    {
        $from = reset($columns)->copy()->startOfDay();
        $to = $this->step(end($columns)->copy(), 1)->startOfDay();

        return EquipmentMeterReading::query()
            ->whereIn('equipment_id', $equipmentIds)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<', $to)
            ->orderBy('recorded_at')
            ->get(['equipment_id', 'reading_value', 'delta', 'is_reset', 'recorded_at']);
    }

    /**
     * @param  Collection<int, Equipment>  $equipment
     * @param  list<Carbon>  $columns
     * @param  Collection<int, EquipmentMeterReading>  $readings
     * @return array{rows: list<array<string, mixed>>, columnTotals: array<string, float>, grandTotal: float}
     */
    private function buildMatrix(Collection $equipment, array $columns, Collection $readings): array
    {
        $byEquipment = $readings->groupBy('equipment_id');
        $columnKeys = array_map(fn (Carbon $d): string => $d->format('Y-m-d'), $columns);
        $columnTotals = array_fill_keys($columnKeys, 0.0);
        $grandTotal = 0.0;
        $rows = [];

        foreach ($equipment as $eq) {
            $eqReadings = $byEquipment->get($eq->id, collect());
            $cells = [];
            $rowTotal = 0.0;

            foreach ($columns as $date) {
                $key = $date->format('Y-m-d');
                $inPeriod = $eqReadings->filter(fn (EquipmentMeterReading $r): bool => $this->columnKeyFor($r->recorded_at) === $key);

                if ($inPeriod->isEmpty()) {
                    $cells[$key] = ['filled' => false];

                    continue;
                }

                $latest = $inPeriod->sortByDesc('recorded_at')->first();
                $hours = round((float) $inPeriod->sum('delta'), 1);
                $rowTotal += $hours;
                $columnTotals[$key] += $hours;
                $grandTotal += $hours;

                $cells[$key] = [
                    'filled' => true,
                    'reading' => round((float) $latest->reading_value, 1),
                    'hours' => $hours,
                    'reset' => (bool) $inPeriod->contains(fn (EquipmentMeterReading $r): bool => (bool) $r->is_reset),
                ];
            }

            $rows[] = [
                'id' => $eq->id,
                'code' => $eq->code,
                'name' => $eq->name,
                'cells' => $cells,
                'total' => round($rowTotal, 1),
            ];
        }

        return ['rows' => $rows, 'columnTotals' => $columnTotals, 'grandTotal' => round($grandTotal, 1)];
    }

    // ── Helpers de fecha ──────────────────────────────────────────────────────

    /** La clave de columna a la que pertenece una lectura, según diario o semanal. */
    private function columnKeyFor(CarbonInterface $recordedAt): string
    {
        return $this->alignAnchor($recordedAt->copy())->format('Y-m-d');
    }

    /** Diario: el día tal cual. Semanal: el lunes de su semana. */
    private function alignAnchor(CarbonInterface $date): CarbonInterface
    {
        return $this->stepUnit() === 'week'
            ? $date->startOfWeek(Carbon::MONDAY)
            : $date->startOfDay();
    }

    private function step(Carbon $date, int $amount): Carbon
    {
        return $this->stepUnit() === 'week'
            ? $date->addWeeks($amount)
            : $date->addDays($amount);
    }
}
