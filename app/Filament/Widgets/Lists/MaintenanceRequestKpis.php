<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Filament\Resources\Maintenance\MaintenanceRequest\MaintenanceRequestResource;
use App\Filament\Widgets\ListKpis;
use App\Models\MaintenanceRequest;
use Carbon\CarbonImmutable;

/** Encima de las solicitudes: lo que espera revisión, lo aprobado sin OT y qué tan rápido se responde. */
class MaintenanceRequestKpis extends ListKpis
{
    protected function kpis(): array
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $toReview = MaintenanceRequest::query()->whereIn('status', [MaintenanceRequestStatus::Submitted->value, MaintenanceRequestStatus::UnderReview->value])->count();
        $approvedWithoutOrder = MaintenanceRequest::query()->where('status', MaintenanceRequestStatus::Approved->value)->whereNull('work_order_id')->count();
        $converted = MaintenanceRequest::query()->where('status', MaintenanceRequestStatus::Converted->value)->where('updated_at', '>=', $month)->count();

        $reviewed = MaintenanceRequest::query()
            ->whereNotNull('reviewed_at')
            ->where('created_at', '>=', now()->subDays(90))
            ->get(['created_at', 'reviewed_at']);
        $hours = $reviewed->isEmpty() ? null : $reviewed->avg(fn (MaintenanceRequest $r): float => $r->created_at->diffInMinutes($r->reviewed_at) / 60);

        return [
            [
                'label' => 'Por revisar',
                'value' => number_format($toReview, 0, ',', '.'),
                'detail' => 'Enviadas o en revisión',
                'icon' => 'heroicon-o-inbox-arrow-down',
                'tone' => $toReview > 0 ? 'warning' : 'ok',
                'link' => ['filters' => ['por_revisar' => ['isActive' => true]]],
            ],
            [
                'label' => 'Aprobadas sin OT',
                'value' => number_format($approvedWithoutOrder, 0, ',', '.'),
                'detail' => 'Falta convertirlas en orden de trabajo',
                'icon' => 'heroicon-o-arrow-right-circle',
                'tone' => $approvedWithoutOrder > 0 ? 'warning' : 'ok',
                'link' => ['filters' => ['aprobadas_sin_ot' => ['isActive' => true]]],
            ],
            [
                'label' => 'Convertidas este mes',
                'value' => number_format($converted, 0, ',', '.'),
                'detail' => 'Ya son orden de trabajo',
                'icon' => 'heroicon-o-check-badge',
                'tone' => 'ok',
            ],
            [
                'label' => 'Respuesta promedio',
                'value' => $hours === null ? '—' : ($hours >= 48 ? number_format($hours / 24, 1, ',', '.').' días' : number_format($hours, 0, ',', '.').' h'),
                'detail' => 'De enviada a revisada, últimos 90 días',
                'icon' => 'heroicon-o-clock',
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return MaintenanceRequestResource::getUrl('index', $parameters);
    }
}
