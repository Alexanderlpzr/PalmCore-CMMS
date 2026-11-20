<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Filament\Resources\Alerts\Alert\Pages\ListAlerts;
use App\Filament\Resources\Alerts\AlertResource;
use App\Filament\Widgets\ListKpis;
use App\Models\Alert;
use App\Support\FrondaPalette;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;

/**
 * Encima de las alertas: las abiertas, las críticas entre ellas y cómo va el mes. Cuenta
 * solo lo de la pestaña que se mira —Mantenimiento, SST y Ambiental o Todas—: antes las
 * sumaba todas y las de los hallazgos parecían de mantenimiento (2026-11-19).
 */
class AlertKpis extends ListKpis
{
    /** La pestaña de la lista; la pasa {@see ListAlerts::getWidgetData()}. */
    #[Reactive]
    public ?string $activeTab = null;

    protected function kpis(): array
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $open = $this->alerts()->where('status', AlertStatus::Open->value)->count();
        $critical = $this->alerts()->where('status', AlertStatus::Open->value)->where('severity', AlertSeverity::Critical->value)->count();
        $created = $this->alerts()->where('created_at', '>=', $month)->count();
        $closed = $this->alerts()->where('status', '!=', AlertStatus::Open->value)->where('closed_at', '>=', $month)->count();

        return [
            [
                'label' => 'Abiertas',
                'value' => number_format($open, 0, ',', '.'),
                'detail' => 'Esperan que alguien las atienda',
                'icon' => 'heroicon-o-bell-alert',
                'tone' => $open > 0 ? 'warning' : 'ok',
                'link' => $this->withTab(['filters' => ['status' => ['value' => AlertStatus::Open->value]]]),
            ],
            [
                'label' => 'Críticas abiertas',
                'value' => number_format($critical, 0, ',', '.'),
                'detail' => 'Las primeras que hay que mirar',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $critical > 0 ? 'danger' : 'ok',
                'percent' => $open > 0 ? $critical * 100 / $open : null,
                'color' => '#dc2626',
                'link' => $this->withTab(['filters' => ['status' => ['value' => AlertStatus::Open->value], 'severity' => ['value' => AlertSeverity::Critical->value]]]),
            ],
            [
                'label' => 'Nuevas este mes',
                'value' => number_format($created, 0, ',', '.'),
                'detail' => 'Desde el 1 de '.$month->locale('es')->translatedFormat('F'),
                'icon' => 'heroicon-o-inbox',
            ],
            [
                'label' => 'Cerradas este mes',
                'value' => number_format($closed, 0, ',', '.'),
                'detail' => 'Resueltas o descartadas',
                'icon' => 'heroicon-o-check-badge',
                'tone' => 'ok',
            ],
        ];
    }

    protected function bars(): ?array
    {
        $months = collect(range(5, 0))->map(fn (int $i): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonthsNoOverflow($i));
        $colors = [
            AlertSeverity::Critical->value => '#dc2626',
            AlertSeverity::Warning->value => '#f59e0b',
            AlertSeverity::Info->value => FrondaPalette::Petrol[400],
        ];

        return [
            'title' => 'Alertas por mes',
            'labels' => $months->map(fn (CarbonImmutable $m): string => ucfirst($m->locale('es')->translatedFormat('M')))->all(),
            'series' => collect(AlertSeverity::cases())->map(fn (AlertSeverity $severity): array => [
                'label' => $severity->label(),
                'color' => $colors[$severity->value] ?? '#94a3b8',
                'values' => $months->map(fn (CarbonImmutable $m): int => $this->alerts()
                    ->where('severity', $severity->value)
                    ->whereBetween('created_at', [$m, $m->endOfMonth()])
                    ->count())->all(),
            ])->all(),
        ];
    }

    /** Las alertas de la pestaña que se mira. */
    private function alerts(): Builder
    {
        $tab = $this->activeTab ?? 'mantenimiento';

        return Alert::query()
            ->when($tab === 'mantenimiento', fn (Builder $query) => $query->where('category', '!=', AlertCategory::Safety->value))
            ->when($tab === ListAlerts::SAFETY_TAB, fn (Builder $query) => $query->where('category', AlertCategory::Safety->value));
    }

    /**
     * Que el enlace de una tarjeta se quede en la pestaña.
     *
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>
     */
    private function withTab(array $link): array
    {
        return ['tab' => $this->activeTab ?? 'mantenimiento', ...$link];
    }

    protected function listUrl(array $parameters = []): string
    {
        return AlertResource::getUrl('index', $parameters);
    }
}
