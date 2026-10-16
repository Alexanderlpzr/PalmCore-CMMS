<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Filament\Resources\AttendanceDays\AttendanceDayResource;
use App\Filament\Widgets\ListKpis;
use App\Models\AttendanceDay;
use App\Support\Hours;
use Carbon\CarbonImmutable;

/** Encima de «Horas por confirmar»: cuántos días esperan firma, cuáles traen avisos y desde cuándo. */
class AttendanceDayKpis extends ListKpis
{
    protected function kpis(): array
    {
        $pending = AttendanceDay::query()->proposed();
        $count = (clone $pending)->count();
        $warnings = (clone $pending)->withAnomalies()->count();
        $oldest = (clone $pending)->min('work_date');
        $hours = (float) (clone $pending)->sum('worked_hours');
        $oldestDate = $oldest ? CarbonImmutable::parse($oldest) : null;

        return [
            [
                'label' => 'Días por confirmar',
                'value' => number_format($count, 0, ',', '.'),
                'detail' => 'Solo lo confirmado se paga',
                'icon' => 'heroicon-o-clipboard-document-check',
                'tone' => $count > 0 ? 'warning' : 'ok',
                'link' => ['filters' => ['status' => ['value' => AttendanceDayStatus::Propuesta->value]]],
            ],
            [
                'label' => 'Con avisos',
                'value' => number_format($warnings, 0, ',', '.'),
                'detail' => 'Sin salida, extras de más: revisarlos uno por uno',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $warnings > 0 ? 'danger' : 'ok',
                'percent' => $count > 0 ? $warnings * 100 / $count : null,
                'color' => '#dc2626',
                'link' => ['filters' => ['con_anomalias' => ['isActive' => true]]],
            ],
            [
                'label' => 'El más antiguo',
                'value' => $oldestDate ? $oldestDate->format('d/m/Y') : '—',
                'detail' => $oldestDate ? 'Hace '.(int) $oldestDate->diffInDays(CarbonImmutable::today()).' días sin confirmar' : 'No hay días pendientes',
                'icon' => 'heroicon-o-clock',
                'tone' => $oldestDate && $oldestDate->lt(CarbonImmutable::today()->subDays(7)) ? 'warning' : 'neutral',
            ],
            [
                'label' => 'Horas por confirmar',
                'value' => Hours::format($hours) ?: '0 h',
                'detail' => 'Trabajadas en esos días',
                'icon' => 'heroicon-o-clock',
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return AttendanceDayResource::getUrl('index', $parameters);
    }
}
