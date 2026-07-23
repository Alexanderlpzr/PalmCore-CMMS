<?php

namespace App\Filament\Resources\MeterReadings\Concerns;

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Enums\MeterReadingFrequency;
use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El motor de la matriz de horómetros al estilo del Excel de la planta: equipos
 * en las filas, fechas en las columnas, y en cada celda el horómetro leído más
 * las horas trabajadas del período. La frecuencia (diaria o semanal) y la ventana
 * salen de `$this->tab` de la página que lo usa.
 *
 * A diferencia del Excel, las horas se sacan del `delta` que ya calcula
 * EquipmentMeterReadingService —nunca da negativo cuando cambian el dial—. Solo
 * se capturan celdas vacías: corregir una lectura pasada es otro problema.
 *
 * @property string $tab la pestaña activa ('diario'|'semanal'|…) — la declara la página
 */
trait InteractsWithMeterMatrix
{
    /** Fecha más reciente de la ventana visible (Y-m-d). */
    public string $anchor = '';

    /** Valores tecleados pendientes de guardar: draft[equipmentId][dateKey]. */
    public array $draft = [];

    protected function matrixFrequency(): MeterReadingFrequency
    {
        return $this->tab === 'semanal' ? MeterReadingFrequency::Weekly : MeterReadingFrequency::Daily;
    }

    protected function matrixColumnCount(): int
    {
        return $this->tab === 'semanal' ? 8 : 7;
    }

    protected function matrixStepUnit(): string
    {
        return $this->tab === 'semanal' ? 'week' : 'day';
    }

    public function resetAnchor(): void
    {
        $this->anchor = $this->alignAnchor(Carbon::today())->format('Y-m-d');
    }

    // ── Navegación de la ventana ──────────────────────────────────────────────

    public function previousWindow(): void
    {
        $this->anchor = $this->step(Carbon::parse($this->anchor), -$this->matrixColumnCount())->format('Y-m-d');
    }

    public function nextWindow(): void
    {
        $next = $this->step(Carbon::parse($this->anchor), $this->matrixColumnCount());
        $today = $this->alignAnchor(Carbon::today());

        $this->anchor = $next->greaterThan($today) ? $today->format('Y-m-d') : $next->format('Y-m-d');
    }

    public function goToToday(): void
    {
        $this->resetAnchor();
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

    // ── Datos de la matriz ────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function getMatrixData(): array
    {
        $columns = $this->columnDates();
        $equipment = $this->matrixEquipment();

        $readings = $this->readingsIn($equipment->pluck('id'), $columns);
        $matrix = $this->buildMatrix($equipment, $columns, $readings);

        return [
            'columns' => array_map(fn (CarbonInterface $d): array => [
                'key' => $d->format('Y-m-d'),
                'label' => $this->matrixStepUnit() === 'week'
                    ? $d->translatedFormat('d M')
                    : $d->translatedFormat('D d'),
            ], $columns),
            'rows' => $matrix['rows'],
            'columnTotals' => $matrix['columnTotals'],
            'grandTotal' => $matrix['grandTotal'],
            'rangeLabel' => reset($columns)->translatedFormat('d M Y').' — '.end($columns)->translatedFormat('d M Y'),
            'isDaily' => $this->matrixStepUnit() === 'day',
            'canGoNext' => Carbon::parse($this->anchor)->lessThan($this->alignAnchor(Carbon::today())),
        ];
    }

    /**
     * @return list<CarbonInterface> de la más vieja a la más reciente (izquierda → derecha)
     */
    private function columnDates(): array
    {
        $anchor = Carbon::parse($this->anchor);

        $dates = [];
        for ($i = $this->matrixColumnCount() - 1; $i >= 0; $i--) {
            $dates[] = $this->step($anchor->copy(), -$i);
        }

        return $dates;
    }

    /**
     * @return Collection<int, Equipment>
     */
    private function matrixEquipment(): Collection
    {
        return Equipment::query()
            ->where('reading_frequency', $this->matrixFrequency()->value)
            ->whereNotIn('status', [EquipmentStatus::Retired->value, EquipmentStatus::Disposed->value])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'current_meter_reading', 'meter_unit']);
    }

    /**
     * @param  Collection<int, string>  $equipmentIds
     * @param  list<CarbonInterface>  $columns
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
     * @param  list<CarbonInterface>  $columns
     * @param  Collection<int, EquipmentMeterReading>  $readings
     * @return array{rows: list<array<string, mixed>>, columnTotals: array<string, float>, grandTotal: float}
     */
    private function buildMatrix(Collection $equipment, array $columns, Collection $readings): array
    {
        $byEquipment = $readings->groupBy('equipment_id');
        $columnKeys = array_map(fn (CarbonInterface $d): string => $d->format('Y-m-d'), $columns);
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

    private function columnKeyFor(CarbonInterface $recordedAt): string
    {
        return $this->alignAnchor($recordedAt->copy())->format('Y-m-d');
    }

    private function alignAnchor(CarbonInterface $date): CarbonInterface
    {
        return $this->matrixStepUnit() === 'week'
            ? $date->startOfWeek(Carbon::MONDAY)
            : $date->startOfDay();
    }

    private function step(CarbonInterface $date, int $amount): CarbonInterface
    {
        return $this->matrixStepUnit() === 'week'
            ? $date->addWeeks($amount)
            : $date->addDays($amount);
    }
}
