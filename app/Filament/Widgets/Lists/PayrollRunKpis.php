<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Filament\Widgets\ListKpis;
use App\Models\PayrollRun;
use App\Support\FrondaPalette;

/** Encima de las nóminas: la última pagada, las que están en borrador y las que traen avisos. */
class PayrollRunKpis extends ListKpis
{
    protected function kpis(): array
    {
        $last = PayrollRun::query()->where('status', PayrollRunStatus::Cerrada->value)->orderByDesc('period_start')->first();
        $drafts = PayrollRun::query()->where('status', PayrollRunStatus::Borrador->value);
        $draftCount = (clone $drafts)->count();
        $withWarnings = (clone $drafts)->whereHas('entriesWithWarnings')->count();
        $current = (clone $drafts)->whereNotNull('calculated_at')->orderByDesc('period_start')->first();

        return [
            [
                'label' => 'Última nómina cerrada',
                'value' => $last ? '$ '.number_format((float) $last->total_net, 0, ',', '.') : '—',
                'detail' => $last ? $last->name.' · neto de '.$last->employee_count.' trabajadores' : 'Todavía no se ha cerrado ninguna',
                'icon' => 'heroicon-o-banknotes',
                'tone' => 'ok',
                'link' => ['filters' => ['status' => ['value' => PayrollRunStatus::Cerrada->value]]],
            ],
            [
                'label' => 'En borrador',
                'value' => number_format($draftCount, 0, ',', '.'),
                'detail' => 'Se pueden volver a liquidar',
                'icon' => 'heroicon-o-pencil-square',
                'tone' => $draftCount > 0 ? 'warning' : 'neutral',
                'link' => ['filters' => ['status' => ['value' => PayrollRunStatus::Borrador->value]]],
            ],
            [
                'label' => 'Con avisos',
                'value' => number_format($withWarnings, 0, ',', '.'),
                'detail' => 'Borradores con trabajadores por revisar',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => $withWarnings > 0 ? 'danger' : 'ok',
            ],
            [
                'label' => 'Nómina en curso',
                'value' => $current ? '$ '.number_format((float) $current->total_net, 0, ',', '.') : '—',
                'detail' => $current ? $current->name.' · liquidada, sin cerrar' : 'Ningún borrador liquidado',
                'icon' => 'heroicon-o-calculator',
            ],
        ];
    }

    protected function bars(): ?array
    {
        $runs = PayrollRun::query()->whereNotNull('calculated_at')->orderByDesc('period_start')->limit(6)->get()->reverse()->values();

        if ($runs->isEmpty()) {
            return null;
        }

        return [
            'title' => 'Neto por nómina (millones de pesos)',
            'labels' => $runs->map(fn (PayrollRun $r): string => ucfirst($r->period_start->locale('es')->translatedFormat('M')))->all(),
            'series' => [[
                'label' => 'Neto',
                'color' => FrondaPalette::Brand[600],
                'values' => $runs->map(fn (PayrollRun $r): float => round((float) $r->total_net / 1_000_000, 1))->all(),
            ]],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return PayrollRunResource::getUrl('index', $parameters);
    }
}
