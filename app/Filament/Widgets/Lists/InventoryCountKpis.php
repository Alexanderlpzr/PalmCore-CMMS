<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Inventory\Enums\InventoryCountStatus;
use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Domain\Inventory\Services\InventoryCountService;
use App\Filament\Resources\Inventory\InventoryCount\InventoryCountResource;
use App\Filament\Widgets\ListKpis;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\InventoryTransaction;
use App\Models\Tenant;
use Filament\Facades\Filament;

/**
 * Encima de los conteos: lo que está abierto, lo que ya toca contar, qué tan exacto está
 * el inventario —la parte de lo contado que cuadró— y cuánto movió el último conteo.
 */
class InventoryCountKpis extends ListKpis
{
    protected function kpis(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $open = InventoryCount::query()->where('status', InventoryCountStatus::Open->value)->count();
        $due = app(InventoryCountService::class)->dueCount($tenant->id);

        // Exactitud: de lo contado en los conteos aplicados de los últimos tres meses,
        // la parte que encontró lo mismo que decía el sistema.
        $counted = InventoryCountLine::query()
            ->whereNotNull('counted_quantity')
            ->whereHas('inventoryCount', fn ($count) => $count->where('status', InventoryCountStatus::Applied->value)->where('applied_at', '>=', now()->subMonths(3)));
        $countedLines = (clone $counted)->count();
        $exactLines = (clone $counted)->whereColumn('counted_quantity', 'expected_quantity')->count();
        $accuracy = $countedLines > 0 ? $exactLines * 100 / $countedLines : null;

        $last = InventoryCount::query()->where('status', InventoryCountStatus::Applied->value)->latest('applied_at')->first();
        $lastValue = $last ? (float) InventoryTransaction::query()
            ->where('type', InventoryTransactionType::Adjustment->value)
            ->where('notes', 'Conteo '.$last->count_number)
            ->selectRaw('coalesce(sum(quantity * unit_cost), 0) as valor')
            ->value('valor') : 0.0;

        return [
            [
                'label' => 'Abiertos',
                'value' => number_format($open, 0, ',', '.'),
                'detail' => $open > 0 ? 'Se están contando: aplíquelos al terminar' : 'Ningún conteo en curso',
                'icon' => 'heroicon-o-clipboard-document-list',
                'tone' => $open > 0 ? 'warning' : 'neutral',
                'link' => ['filters' => ['status' => ['value' => InventoryCountStatus::Open->value]]],
            ],
            [
                'label' => 'Por contar',
                'value' => number_format($due, 0, ',', '.'),
                'detail' => 'Les toca conteo cíclico según su ABC',
                'icon' => 'heroicon-o-calendar-days',
                'tone' => $due > 0 ? 'warning' : 'ok',
                'url' => InventoryCountResource::canCreate() ? InventoryCountResource::getUrl('create') : null,
            ],
            [
                'label' => 'Exactitud del inventario',
                'value' => $accuracy === null ? '—' : number_format($accuracy, 0, ',', '.').' %',
                'detail' => $countedLines > 0 ? "{$exactLines} de {$countedLines} contados cuadraron (3 meses)" : 'Sin conteos aplicados en tres meses',
                'icon' => 'heroicon-o-check-badge',
                'tone' => match (true) {
                    $accuracy === null => 'neutral',
                    $accuracy >= 95 => 'ok',
                    $accuracy >= 85 => 'warning',
                    default => 'danger',
                },
                'percent' => $accuracy,
            ],
            [
                'label' => 'Último conteo',
                'value' => $last ? ($lastValue < 0 ? '−' : '').'$ '.number_format(abs($lastValue), 0, ',', '.') : '—',
                'detail' => $last ? $last->count_number.' · '.$last->applied_at?->format('d/m/Y').' · sobrantes menos faltantes' : 'Todavía no se ha aplicado ninguno',
                'icon' => 'heroicon-o-scale',
                'tone' => $last && $lastValue < 0 ? 'danger' : 'neutral',
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return InventoryCountResource::getUrl('index', $parameters);
    }
}
