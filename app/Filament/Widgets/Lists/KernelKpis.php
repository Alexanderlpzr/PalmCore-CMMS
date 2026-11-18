<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Filament\Resources\Commercial\KernelInventoryCount\KernelInventoryCountResource;
use App\Filament\Widgets\ListKpis;
use App\Models\KernelInventoryCount;
use App\Models\KernelTollLoad;
use App\Models\SalesDispatch;
use Carbon\CarbonImmutable;

/**
 * Encima de la maquila y del inventario de palmiste: lo que hay en bodega según el último
 * corte, el palmiste vendido en el año y la torta que devolvió la maquila con lo que dejó.
 */
class KernelKpis extends ListKpis
{
    protected function kpis(): array
    {
        $year = CarbonImmutable::parse(SalesDispatch::today())->startOfYear()->toDateString();
        $last = KernelInventoryCount::query()->latest('counted_on')->first();
        $pkoSold = (float) SalesDispatch::query()->where('product', CommercialProduct::Pko->value)->whereDate('loaded_on', '>=', $year)->sum('arrival_weight_tons');
        $loads = KernelTollLoad::query()->whereDate('loaded_on', '>=', $year)->get();
        $cake = $loads->sum('cake_kg') / 1000;
        $balance = $loads->sum(fn (KernelTollLoad $load): float => (float) $load->balance());
        $tn = fn (float $v): string => number_format($v, 2, ',', '.').' tn';

        return [
            [
                'label' => 'Palmiste en bodega',
                'value' => $last?->pko_tons !== null ? $tn((float) $last->pko_tons) : '—',
                'detail' => $last ? 'Corte del '.$last->counted_on->format('d/m/Y') : 'Sin cortes de inventario',
                'icon' => 'heroicon-o-archive-box',
            ],
            [
                'label' => 'Torta en bodega',
                'value' => $last?->cake_tons !== null ? $tn((float) $last->cake_tons) : '—',
                'detail' => $last ? 'Mismo corte' : null,
                'icon' => 'heroicon-o-cube',
            ],
            [
                'label' => 'Palmiste despachado en '.substr($year, 0, 4),
                'value' => $tn($pkoSold),
                'detail' => 'Por peso de llegada',
                'icon' => 'heroicon-o-truck',
            ],
            [
                'label' => 'Torta de maquila en '.substr($year, 0, 4),
                'value' => $tn($cake),
                'detail' => $loads->count().' cargues · saldo $'.number_format($balance / 1_000_000, 1, ',', '.').' M',
                'icon' => 'heroicon-o-arrows-right-left',
                'tone' => $balance < 0 ? 'warning' : 'neutral',
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        // Las tarjetas no filtran: se queda en la lista donde esté.
        return KernelInventoryCountResource::getUrl('index', $parameters);
    }
}
