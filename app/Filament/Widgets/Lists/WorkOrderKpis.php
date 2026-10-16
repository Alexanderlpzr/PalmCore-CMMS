<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Filament\Widgets\ListKpis;
use App\Models\WorkOrder;
use App\Support\FrondaPalette;
use Carbon\CarbonImmutable;

/** Encima de las órdenes de trabajo: las abiertas, las vencidas, lo cerrado y lo gastado en el mes. */
class WorkOrderKpis extends ListKpis
{
    protected function kpis(): array
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $open = WorkOrder::query()->open()->count();
        $overdue = WorkOrder::query()->open()->whereNotNull('planned_end_at')->where('planned_end_at', '<', now())->count();
        $closed = WorkOrder::query()->where('status', WorkOrderStatus::Closed->value)->where('closed_at', '>=', $month);
        $closedCount = (clone $closed)->count();
        $cost = (float) (clone $closed)->sum('actual_cost_total');

        return [
            [
                'label' => 'Abiertas',
                'value' => number_format($open, 0, ',', '.'),
                'detail' => 'Trabajo pendiente',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'link' => ['tab' => 'abiertas'],
            ],
            [
                'label' => 'Vencidas',
                'value' => number_format($overdue, 0, ',', '.'),
                'detail' => 'Abiertas con la fecha planificada pasada',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $overdue > 0 ? 'danger' : 'ok',
                'percent' => $open > 0 ? $overdue * 100 / $open : null,
                'color' => '#dc2626',
                'link' => ['tab' => 'abiertas', 'filters' => ['vencidas' => ['isActive' => true]]],
            ],
            [
                'label' => 'Cerradas este mes',
                'value' => number_format($closedCount, 0, ',', '.'),
                'detail' => 'Desde el 1 de '.$month->locale('es')->translatedFormat('F'),
                'icon' => 'heroicon-o-check-badge',
                'tone' => 'ok',
                'link' => ['tab' => 'historico'],
            ],
            [
                'label' => 'Costo del mes',
                'value' => '$ '.number_format($cost, 0, ',', '.'),
                'detail' => 'Mano de obra, repuestos y servicios de las cerradas',
                'icon' => 'heroicon-o-banknotes',
            ],
        ];
    }

    protected function bars(): ?array
    {
        $months = collect(range(5, 0))->map(fn (int $i): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonthsNoOverflow($i));
        $corrective = [WorkOrderType::Corrective->value, WorkOrderType::Emergency->value];
        $preventive = [WorkOrderType::Preventive->value, WorkOrderType::Predictive->value];
        $count = fn (CarbonImmutable $m, array $types): int => WorkOrder::query()
            ->whereIn('work_order_type', $types)
            ->whereBetween('created_at', [$m, $m->endOfMonth()])
            ->count();

        return [
            'title' => 'OT creadas por mes',
            'labels' => $months->map(fn (CarbonImmutable $m): string => ucfirst($m->locale('es')->translatedFormat('M')))->all(),
            'series' => [
                ['label' => 'Correctivas', 'color' => FrondaPalette::Petrol[600], 'values' => $months->map(fn ($m): int => $count($m, $corrective))->all()],
                ['label' => 'Preventivas', 'color' => FrondaPalette::Brand[600], 'values' => $months->map(fn ($m): int => $count($m, $preventive))->all()],
                ['label' => 'Mejoras', 'color' => '#94a3b8', 'values' => $months->map(fn ($m): int => $count($m, [WorkOrderType::Improvement->value]))->all()],
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return WorkOrderResource::getUrl('index', $parameters);
    }
}
