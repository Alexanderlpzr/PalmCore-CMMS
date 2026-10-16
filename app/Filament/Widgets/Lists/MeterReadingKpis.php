<?php

namespace App\Filament\Widgets\Lists;

use App\Filament\Resources\MeterReadings\MeterReadingResource;
use App\Filament\Widgets\ListKpis;
use App\Models\EquipmentMeterReading;
use Carbon\CarbonImmutable;

/** Encima de los horómetros: lo leído hoy, los equipos que faltan y los cambios de horómetro. */
class MeterReadingKpis extends ListKpis
{
    protected function kpis(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $month = $today->startOfMonth();
        $readToday = EquipmentMeterReading::query()->where('recorded_at', '>=', $today)->distinct()->count('equipment_id');
        $withMeter = EquipmentMeterReading::query()->where('recorded_at', '>=', $today->subDays(30))->distinct()->count('equipment_id');
        $readingsMonth = EquipmentMeterReading::query()->where('recorded_at', '>=', $month)->count();
        $resets = EquipmentMeterReading::query()->where('is_reset', true)->where('recorded_at', '>=', $month)->count();

        return [
            [
                'label' => 'Leídos hoy',
                'value' => $readToday.' de '.$withMeter,
                'detail' => 'Equipos con lectura hoy, de los que se leen',
                'icon' => 'heroicon-o-clock',
                'percent' => $withMeter > 0 ? $readToday * 100 / $withMeter : null,
                'color' => '#1a7e42',
                'tone' => $withMeter > 0 && $readToday < $withMeter ? 'warning' : 'ok',
            ],
            [
                'label' => 'Faltan hoy',
                'value' => number_format(max(0, $withMeter - $readToday), 0, ',', '.'),
                'detail' => 'Se leyeron este mes y hoy todavía no',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $withMeter - $readToday > 0 ? 'warning' : 'ok',
            ],
            [
                'label' => 'Lecturas del mes',
                'value' => number_format($readingsMonth, 0, ',', '.'),
                'detail' => 'Desde el 1 de '.$month->locale('es')->translatedFormat('F'),
                'icon' => 'heroicon-o-document-text',
            ],
            [
                'label' => 'Cambios de horómetro',
                'value' => number_format($resets, 0, ',', '.'),
                'detail' => 'Este mes: el contador volvió a empezar',
                'icon' => 'heroicon-o-arrow-path',
                'tone' => $resets > 0 ? 'warning' : 'neutral',
                'link' => ['filters' => ['is_reset' => ['isActive' => true]]],
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return MeterReadingResource::getUrl('index', $parameters);
    }
}
