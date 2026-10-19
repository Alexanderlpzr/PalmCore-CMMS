<?php

namespace App\Filament\Widgets\Inventory;

use App\Domain\Inventory\Enums\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\WarehouseSparePart;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * La tarjeta grande de la ficha del conteo: cuánto va contado y cuántas ubicaciones
 * quedaron completas. Las diferencias solo aparecen cuando el conteo se cerró: mientras se
 * cuenta, el conteo es ciego.
 */
class InventoryCountProgress extends Widget
{
    protected string $view = 'filament.widgets.inventory-count-progress';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Model $record = null;

    /** La planilla de abajo avisa cada vez que se anota una cantidad. */
    #[On('conteo-actualizado')]
    public function refreshProgress(): void {}

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var InventoryCount $count */
        $count = $this->record->refresh()->loadMissing('warehouse:id,name');
        $lines = $count->lines()->get();
        $counted = $lines->whereNotNull('counted_quantity');
        $bins = $lines->groupBy(fn (InventoryCountLine $line): string => $line->bin_location ?? 'Sin ubicación');
        $binsDone = $bins->filter(fn ($group): bool => $group->every(fn (InventoryCountLine $line): bool => $line->counted_quantity !== null))->count();

        $costs = WarehouseSparePart::query()
            ->where('warehouse_id', $count->warehouse_id)
            ->whereIn('spare_part_id', $lines->pluck('spare_part_id'))
            ->pluck('average_unit_cost', 'spare_part_id');
        $value = fn ($group): float => round((float) $group->sum(fn (InventoryCountLine $line): float => (float) $line->difference() * (float) ($costs[$line->spare_part_id] ?? 0)), 0);
        $short = $counted->filter(fn (InventoryCountLine $line): bool => $line->difference() < 0);
        $over = $counted->filter(fn (InventoryCountLine $line): bool => $line->difference() > 0);

        return [
            'count' => $count,
            'open' => $count->status === InventoryCountStatus::Open,
            'total' => $lines->count(),
            'counted' => $counted->count(),
            'countedToday' => $counted->filter(fn (InventoryCountLine $line): bool => $line->counted_at?->isToday() ?? false)->count(),
            'bins' => $bins->count(),
            'binsDone' => $binsDone,
            'exact' => $counted->filter(fn (InventoryCountLine $line): bool => $line->difference() == 0)->count(),
            'short' => $short->count(),
            'shortValue' => $value($short),
            'over' => $over->count(),
            'overValue' => $value($over),
        ];
    }
}
