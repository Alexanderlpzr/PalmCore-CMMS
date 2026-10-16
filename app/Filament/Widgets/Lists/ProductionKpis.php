<?php

namespace App\Filament\Widgets\Lists;

use App\Filament\Resources\ProductionCalendar\ProductionCalendarResource;
use App\Filament\Widgets\ListKpis;
use App\Models\ProductionCalendarDay;
use App\Support\FrondaPalette;
use Carbon\CarbonImmutable;

/** Encima de la producción: las toneladas y horas del mes, la productividad y su tendencia. */
class ProductionKpis extends ListKpis
{
    protected function kpis(): array
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $days = ProductionCalendarDay::query()->whereBetween('calendar_date', [$month->toDateString(), $month->endOfMonth()->toDateString()]);
        $tons = (float) (clone $days)->sum('processed_tons');
        $hours = (float) (clone $days)->sum('programmed_hours');
        $captured = (clone $days)->where('processed_tons', '>', 0)->count();
        $previous = (float) ProductionCalendarDay::query()
            ->whereBetween('calendar_date', [$month->subMonthNoOverflow()->toDateString(), $month->subMonthNoOverflow()->endOfMonth()->toDateString()])
            ->sum('processed_tons');

        return [
            [
                'label' => 'Toneladas del mes',
                'value' => number_format($tons, 1, ',', '.').' t',
                'detail' => $previous > 0 ? 'Mes anterior: '.number_format($previous, 1, ',', '.').' t' : 'De fruto procesado',
                'color' => FrondaPalette::Brand[600],
                'icon' => 'heroicon-o-scale',
            ],
            [
                'label' => 'Horas programadas',
                'value' => number_format($hours, 1, ',', '.').' h',
                'detail' => 'Del mes',
                'icon' => 'heroicon-o-clock',
            ],
            [
                'label' => 'Productividad',
                'value' => $hours > 0 ? number_format($tons / $hours, 2, ',', '.').' t/h' : '—',
                'detail' => 'Toneladas por hora programada',
                'icon' => 'heroicon-o-arrow-trending-up',
            ],
            [
                'label' => 'Días registrados',
                'value' => $captured.' de '.$month->daysInMonth,
                'detail' => 'Con toneladas capturadas',
                'icon' => 'heroicon-o-calendar-days',
                'percent' => $captured * 100 / max(1, now()->day),
                'color' => FrondaPalette::Petrol[600],
            ],
        ];
    }

    protected function bars(): ?array
    {
        $months = collect(range(5, 0))->map(fn (int $i): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonthsNoOverflow($i));

        return [
            'title' => 'Toneladas por mes',
            'labels' => $months->map(fn (CarbonImmutable $m): string => ucfirst($m->locale('es')->translatedFormat('M')))->all(),
            'series' => [[
                'label' => 'Toneladas',
                'color' => FrondaPalette::Brand[600],
                'values' => $months->map(fn (CarbonImmutable $m): float => round((float) ProductionCalendarDay::query()
                    ->whereBetween('calendar_date', [$m->toDateString(), $m->endOfMonth()->toDateString()])
                    ->sum('processed_tons'), 1))->all(),
            ]],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return ProductionCalendarResource::getUrl('index', $parameters);
    }
}
