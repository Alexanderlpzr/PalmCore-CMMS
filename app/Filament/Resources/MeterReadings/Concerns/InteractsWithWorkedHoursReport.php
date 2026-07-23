<?php

namespace App\Filament\Resources\MeterReadings\Concerns;

use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

/**
 * El consolidado de horas trabajadas por equipo (mensual/anual) directo del
 * horómetro: suma los `delta` del período elegido. Reemplaza la vieja pantalla
 * manual «Horas trabajadas», que tecleaba lo mismo por segunda vez y podía
 * contradecir al dial.
 */
trait InteractsWithWorkedHoursReport
{
    /** Modo del reporte: 'mensual' | 'anual'. */
    public string $whMode = 'mensual';

    public int $whYear = 0;

    public int $whMonth = 0;

    public function initWorkedHoursReport(): void
    {
        $this->whYear = (int) now()->year;
        $this->whMonth = (int) now()->month;
    }

    /**
     * @return array<string, mixed>
     */
    public function workedHoursReport(): array
    {
        [$from, $to] = $this->whMode === 'anual'
            ? [Carbon::create($this->whYear, 1, 1)->startOfYear(), Carbon::create($this->whYear, 1, 1)->endOfYear()]
            : [Carbon::create($this->whYear, $this->whMonth, 1)->startOfMonth(), Carbon::create($this->whYear, $this->whMonth, 1)->endOfMonth()];

        $rows = app(EquipmentMeterReadingService::class)->workedHoursSummary(Filament::getTenant()->id, $from, $to);

        return [
            'mode' => $this->whMode,
            'rows' => $rows,
            'total' => round(array_sum(array_column($rows, 'total_hours')), 1),
            'periodLabel' => $this->whMode === 'anual'
                ? (string) $this->whYear
                : Carbon::create($this->whYear, $this->whMonth, 1)->translatedFormat('F Y'),
            'years' => range((int) now()->year, (int) now()->year - 4),
            'months' => collect(range(1, 12))
                ->mapWithKeys(fn (int $m): array => [$m => Carbon::create(2000, $m, 1)->translatedFormat('F')])
                ->all(),
        ];
    }
}
