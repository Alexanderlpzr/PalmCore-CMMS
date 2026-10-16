<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Filament\Resources\Maintenance\MaintenancePlan\MaintenancePlanResource;
use App\Filament\Widgets\ListKpis;
use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;

/** Encima de los planes: los activos, los vencidos, los de la semana y lo preventivo cerrado en el mes. */
class MaintenancePlanKpis extends ListKpis
{
    protected function kpis(): array
    {
        $active = MaintenancePlan::query()->active()->count();
        $overdue = MaintenancePlan::query()->active()->whereHas('schedule', fn ($q) => $q->where('next_due_at', '<', now()))->count();
        $soon = MaintenancePlan::query()->active()->whereHas('schedule', fn ($q) => $q->whereBetween('next_due_at', [now(), now()->addDays(7)]))->count();
        $done = WorkOrder::query()
            ->whereIn('work_order_type', [WorkOrderType::Preventive->value, WorkOrderType::Predictive->value])
            ->where('status', WorkOrderStatus::Closed->value)
            ->where('closed_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->count();

        return [
            [
                'label' => 'Planes activos',
                'value' => number_format($active, 0, ',', '.'),
                'detail' => 'Generan órdenes preventivas',
                'icon' => 'heroicon-o-calendar-days',
                'link' => ['filters' => ['is_active' => ['value' => 1]]],
            ],
            [
                'label' => 'Vencidos',
                'value' => number_format($overdue, 0, ',', '.'),
                'detail' => 'La fecha de su próxima ejecución ya pasó',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $overdue > 0 ? 'danger' : 'ok',
                'percent' => $active > 0 ? $overdue * 100 / $active : null,
                'color' => '#dc2626',
                'link' => ['filters' => ['vencidos' => ['isActive' => true]]],
            ],
            [
                'label' => 'Próximos 7 días',
                'value' => number_format($soon, 0, ',', '.'),
                'detail' => 'Para preparar repuestos y personal',
                'icon' => 'heroicon-o-clock',
                'tone' => $soon > 0 ? 'warning' : 'neutral',
                'link' => ['filters' => ['proximos' => ['isActive' => true]]],
            ],
            [
                'label' => 'Preventivas cerradas',
                'value' => number_format($done, 0, ',', '.'),
                'detail' => 'Este mes',
                'icon' => 'heroicon-o-check-badge',
                'tone' => 'ok',
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return MaintenancePlanResource::getUrl('index', $parameters);
    }
}
