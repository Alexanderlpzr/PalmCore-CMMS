<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Assets\Enums\IssueSeverity;
use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use App\Filament\Widgets\ListKpis;
use App\Models\EquipmentIssueReport;
use Carbon\CarbonImmutable;

/** Encima de los reportes de novedad: los que nadie ha visto y los críticos entre ellos. */
class IssueReportKpis extends ListKpis
{
    protected function kpis(): array
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $open = EquipmentIssueReport::query()->open()->count();
        $critical = EquipmentIssueReport::query()->open()->whereIn('severity', [IssueSeverity::Critical->value, IssueSeverity::High->value])->count();
        $received = EquipmentIssueReport::query()->where('created_at', '>=', $month)->count();
        $attended = EquipmentIssueReport::query()->attended()->where('created_at', '>=', $month)->count();

        return [
            [
                'label' => 'Sin atender',
                'value' => number_format($open, 0, ',', '.'),
                'detail' => 'Nadie los ha revisado',
                'icon' => 'heroicon-o-bell-alert',
                'tone' => $open > 0 ? 'warning' : 'ok',
                'link' => ['tab' => 'pendientes'],
            ],
            [
                'label' => 'Críticos o altos',
                'value' => number_format($critical, 0, ',', '.'),
                'detail' => 'Entre los sin atender',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $critical > 0 ? 'danger' : 'ok',
                'percent' => $open > 0 ? $critical * 100 / $open : null,
                'color' => '#dc2626',
                'link' => ['tab' => 'pendientes', 'filters' => ['severity' => ['value' => IssueSeverity::Critical->value]]],
            ],
            [
                'label' => 'Recibidos este mes',
                'value' => number_format($received, 0, ',', '.'),
                'detail' => 'Desde el 1 de '.$month->locale('es')->translatedFormat('F'),
                'icon' => 'heroicon-o-inbox',
            ],
            [
                'label' => 'Atendidos este mes',
                'value' => number_format($attended, 0, ',', '.'),
                'detail' => $received > 0 ? round($attended * 100 / $received).' % de los recibidos' : 'Sin reportes este mes',
                'icon' => 'heroicon-o-check-badge',
                'tone' => 'ok',
                'link' => ['tab' => 'atendidos'],
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return IssueReportResource::getUrl('index', $parameters);
    }
}
