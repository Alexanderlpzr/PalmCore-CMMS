<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageConfirmationStatus;
use App\Filament\Resources\Downtime\DowntimeEventResource;
use App\Filament\Widgets\ListKpis;
use App\Models\EquipmentDowntimeEvent;
use App\Support\FrondaPalette;
use Carbon\CarbonImmutable;

/** Encima de las paradas de planta: las horas paradas del mes, las que siguen abiertas y las sin firmar. */
class DowntimeKpis extends ListKpis
{
    protected function kpis(): array
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $events = EquipmentDowntimeEvent::query()->where('started_at', '>=', $month);
        $count = (clone $events)->count();
        $hours = (float) (clone $events)->sum('duration_minutes') / 60;
        $ongoing = EquipmentDowntimeEvent::query()->whereNull('ended_at')->count();
        $unsigned = EquipmentDowntimeEvent::query()->whereNotNull('ended_at')->where('confirmation_status', StoppageConfirmationStatus::Pending->value)->count();

        return [
            [
                'label' => 'Horas paradas del mes',
                'value' => number_format($hours, 1, ',', '.').' h',
                'detail' => $count.' '.($count === 1 ? 'paro' : 'paros').' desde el 1 de '.$month->locale('es')->translatedFormat('F'),
                'icon' => 'heroicon-o-pause-circle',
                'color' => FrondaPalette::Petrol[600],
            ],
            [
                'label' => 'En curso',
                'value' => number_format($ongoing, 0, ',', '.'),
                'detail' => 'Paros que todavía no se cierran',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $ongoing > 0 ? 'danger' : 'ok',
                'link' => ['filters' => ['ended_at' => ['value' => 0]]],
            ],
            [
                'label' => 'Sin firmar',
                'value' => number_format($unsigned, 0, ',', '.'),
                'detail' => 'Falta la firma de producción',
                'icon' => 'heroicon-o-pencil-square',
                'tone' => $unsigned > 0 ? 'warning' : 'ok',
                'link' => ['filters' => ['confirmation_status' => ['value' => StoppageConfirmationStatus::Pending->value]]],
            ],
            [
                'label' => 'Sin clasificar',
                'value' => number_format($this->unclassified(), 0, ',', '.'),
                'detail' => 'Sin causa: hay que decir qué se rompió',
                'icon' => 'heroicon-o-question-mark-circle',
                'tone' => $this->unclassified() > 0 ? 'warning' : 'ok',
                'link' => ['filters' => ['unclassified' => ['isActive' => true]]],
            ],
        ];
    }

    protected function bars(): ?array
    {
        $months = collect(range(5, 0))->map(fn (int $i): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonthsNoOverflow($i));
        $colors = [
            ReportedStoppageType::Maintenance->value => '#dc2626',
            ReportedStoppageType::Operational->value => '#f59e0b',
            ReportedStoppageType::Scheduled->value => FrondaPalette::Petrol[400],
            ReportedStoppageType::External->value => '#94a3b8',
        ];

        return [
            'title' => 'Horas paradas por tipo',
            'labels' => $months->map(fn (CarbonImmutable $m): string => ucfirst($m->locale('es')->translatedFormat('M')))->all(),
            'series' => collect(ReportedStoppageType::cases())->map(fn (ReportedStoppageType $type): array => [
                'label' => $type->label(),
                'color' => $colors[$type->value] ?? '#94a3b8',
                'values' => $months->map(fn (CarbonImmutable $m): float => round((float) EquipmentDowntimeEvent::query()
                    ->where('reported_type', $type->value)
                    ->whereBetween('started_at', [$m, $m->endOfMonth()])
                    ->sum('duration_minutes') / 60, 1))->all(),
            ])->all(),
        ];
    }

    private function unclassified(): int
    {
        return once(fn (): int => EquipmentDowntimeEvent::query()
            ->where('was_planned', false)
            ->where(fn ($q) => $q->whereNull('stoppage_category')->orWhere('stoppage_category', StoppageCategory::Other->value))
            ->count());
    }

    protected function listUrl(array $parameters = []): string
    {
        return DowntimeEventResource::getUrl('index', $parameters);
    }
}
