<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\InventoryCountScope;
use App\Domain\Inventory\Enums\InventoryCountStatus;
use App\Domain\Inventory\Enums\SparePartAbcClassification;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los conteos de inventario, como los ajustes de Odoo: se abre el conteo con lo que el
 * sistema dice que hay, se cuenta en el estante —sin ver esa cifra— y al aplicarlo cada
 * repuesto contado queda como ajuste en el kardex con su diferencia.
 */
class InventoryCountService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function start(
        Warehouse $warehouse,
        InventoryCountScope $scope,
        User $createdBy,
        ?string $location = null,
        ?string $notes = null,
    ): InventoryCount {
        $stock = $this->stockInScope($warehouse, $scope, $location)->get();

        if ($stock->isEmpty()) {
            throw new \RuntimeException('No hay repuestos para contar con ese alcance.');
        }

        return DB::transaction(function () use ($warehouse, $scope, $createdBy, $location, $notes, $stock): InventoryCount {
            $count = InventoryCount::create([
                'tenant_id' => $warehouse->tenant_id,
                'warehouse_id' => $warehouse->id,
                'count_number' => $this->nextNumber($warehouse->tenant_id),
                'status' => InventoryCountStatus::Open,
                'scope' => $scope,
                'location_filter' => $scope === InventoryCountScope::Location ? $location : null,
                'notes' => $notes,
                'created_by' => $createdBy->id,
            ]);

            foreach ($stock as $row) {
                $count->lines()->create([
                    'tenant_id' => $warehouse->tenant_id,
                    'spare_part_id' => $row->spare_part_id,
                    'bin_location' => $row->bin_location,
                    'expected_quantity' => (float) $row->current_stock,
                ]);
            }

            return $count;
        });
    }

    public function record(InventoryCountLine $line, ?float $quantity, User $countedBy): InventoryCountLine
    {
        if (! $line->inventoryCount->isOpen()) {
            throw new \RuntimeException('Este conteo ya está cerrado.');
        }

        if ($quantity !== null && $quantity < 0) {
            throw new \InvalidArgumentException('Counted quantity cannot be negative.');
        }

        $line->update([
            'counted_quantity' => $quantity,
            'counted_by' => $quantity === null ? null : $countedBy->id,
            'counted_at' => $quantity === null ? null : now(),
        ]);

        return $line;
    }

    /**
     * Pasa al inventario lo contado: un ajuste por repuesto, también los que cuadraron,
     * para que quede la fecha del conteo. Lo que no se contó no se toca.
     *
     * @return array{counted: int, adjusted: int, value: float}
     */
    public function apply(InventoryCount $count, User $appliedBy): array
    {
        if (! $count->isOpen()) {
            throw new \RuntimeException('Este conteo ya está cerrado.');
        }

        $lines = $count->lines()->whereNotNull('counted_quantity')->with('sparePart')->get();

        if ($lines->isEmpty()) {
            throw new \RuntimeException('Todavía no se ha contado ningún repuesto.');
        }

        return DB::transaction(function () use ($count, $appliedBy, $lines): array {
            $warehouse = $count->warehouse;
            $adjusted = 0;
            $value = 0.0;

            foreach ($lines as $line) {
                $average = (float) (WarehouseSparePart::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('spare_part_id', $line->spare_part_id)
                    ->value('average_unit_cost') ?? $line->sparePart->unit_cost);

                $movement = $this->inventoryService->adjustStock(
                    $warehouse, $line->sparePart, (float) $line->counted_quantity, $average, $appliedBy, "Conteo {$count->count_number}",
                );

                if ((float) $movement->quantity !== 0.0) {
                    $adjusted++;
                    $value += (float) $movement->quantity * $average;
                }
            }

            $count->update([
                'status' => InventoryCountStatus::Applied,
                'applied_by' => $appliedBy->id,
                'applied_at' => now(),
            ]);

            return ['counted' => $lines->count(), 'adjusted' => $adjusted, 'value' => round($value, 2)];
        });
    }

    public function cancel(InventoryCount $count): void
    {
        if (! $count->isOpen()) {
            throw new \RuntimeException('Este conteo ya está cerrado.');
        }

        $count->update(['status' => InventoryCountStatus::Cancelled]);
    }

    /**
     * Cuántos repuestos ya deberían haberse contado, según su clase ABC, en todos los
     * almacenes.
     */
    public function dueCount(string $tenantId): int
    {
        return $this->dueQuery(WarehouseSparePart::query()->forTenant($tenantId))->count();
    }

    /** @return Collection<int, string> las ubicaciones del almacén, para elegir una */
    public function locations(Warehouse $warehouse): Collection
    {
        return WarehouseSparePart::query()
            ->where('warehouse_id', $warehouse->id)
            ->whereNotNull('bin_location')
            ->distinct()
            ->orderBy('bin_location')
            ->pluck('bin_location');
    }

    /** @return Builder<WarehouseSparePart> */
    private function stockInScope(Warehouse $warehouse, InventoryCountScope $scope, ?string $location): Builder
    {
        $query = WarehouseSparePart::query()
            ->where('warehouse_id', $warehouse->id)
            ->whereHas('sparePart', fn (Builder $part) => $part->where('is_active', true))
            ->orderBy('bin_location');

        return match ($scope) {
            InventoryCountScope::All => $query,
            InventoryCountScope::Location => $query->where('bin_location', $location),
            InventoryCountScope::ClassA => $query->whereHas('sparePart', fn (Builder $part) => $part->where('abc_classification', SparePartAbcClassification::A->value)),
            InventoryCountScope::Due => $this->dueQuery($query),
        };
    }

    /**
     * Lo que toca contar: nunca contado, o contado hace más días de los que manda su
     * clase (A 30, B 90, C o sin clase 180).
     *
     * @param  Builder<WarehouseSparePart>  $query
     * @return Builder<WarehouseSparePart>
     */
    private function dueQuery(Builder $query): Builder
    {
        $now = CarbonImmutable::now();

        return $query
            ->whereHas('sparePart', fn (Builder $part) => $part->where('is_active', true))
            ->where(function (Builder $due) use ($now): void {
                $due->whereNull('last_counted_at');

                foreach ([SparePartAbcClassification::A, SparePartAbcClassification::B] as $class) {
                    $due->orWhere(fn (Builder $q) => $q
                        ->where('last_counted_at', '<', $now->subDays(InventoryCountScope::cycleDays($class)))
                        ->whereHas('sparePart', fn (Builder $part) => $part->where('abc_classification', $class->value)));
                }

                $due->orWhere(fn (Builder $q) => $q
                    ->where('last_counted_at', '<', $now->subDays(InventoryCountScope::cycleDays(null)))
                    ->whereHas('sparePart', fn (Builder $part) => $part->where(fn (Builder $c) => $c
                        ->whereNull('abc_classification')
                        ->orWhere('abc_classification', SparePartAbcClassification::C->value))));
            });
    }

    private function nextNumber(string $tenantId): string
    {
        $year = date('Y');

        $last = InventoryCount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('count_number', 'like', "CNT-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('count_number')
            ->value('count_number');

        return sprintf('CNT-%s-%04d', $year, $last !== null ? (int) substr($last, -4) + 1 : 1);
    }
}
