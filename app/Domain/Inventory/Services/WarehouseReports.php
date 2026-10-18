<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Domain\Inventory\Enums\SparePartAbcClassification;
use App\Models\InventoryTransaction;
use App\Models\SparePart;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Los reportes del almacén: qué repuestos mueven la plata (ABC por consumo), qué está
 * quieto en el estante, en qué equipos se va y por qué sale lo que sale sin orden.
 * Todo sobre los últimos doce meses del kardex, al costo de cada movimiento.
 */
class WarehouseReports
{
    /** Hasta dónde llega cada clase en el valor acumulado del consumo. */
    private const CLASS_A_LIMIT = 80.0;

    private const CLASS_B_LIMIT = 95.0;

    /**
     * El ABC por consumo, de mayor a menor valor: A hasta el 80 % del valor consumido, B
     * hasta el 95 %, C el resto y lo que no se consumió. Con la rotación de cada uno.
     *
     * @return Collection<int, array{id: string, code: string, name: string, value: float, quantity: float, stock: float, share: float, cumulative: float, proposed: SparePartAbcClassification, current: ?SparePartAbcClassification, turns: ?float}>
     */
    public function abc(Tenant $tenant): Collection
    {
        $consumption = $this->outgoing($tenant)
            ->selectRaw('spare_part_id, sum(abs(quantity) * unit_cost) as valor, sum(abs(quantity)) as cantidad')
            ->groupBy('spare_part_id')
            ->get()
            ->keyBy('spare_part_id');

        $total = (float) $consumption->sum('valor');
        $cumulative = 0.0;

        return SparePart::query()->forTenant($tenant->id)->where('is_active', true)
            ->withSum('warehouseStock', 'current_stock')
            ->get(['id', 'code', 'name', 'abc_classification'])
            ->map(fn (SparePart $part): array => [
                'part' => $part,
                'value' => round((float) ($consumption[$part->id]->valor ?? 0), 2),
                'quantity' => round((float) ($consumption[$part->id]->cantidad ?? 0), 2),
            ])
            ->sortByDesc('value')
            ->values()
            ->map(function (array $row) use ($total, &$cumulative): array {
                $share = $total > 0 ? $row['value'] * 100 / $total : 0.0;
                $before = $cumulative;
                $cumulative += $share;
                $stock = $row['part']->totalStock();

                return [
                    'id' => $row['part']->id,
                    'code' => $row['part']->code,
                    'name' => $row['part']->name,
                    'value' => $row['value'],
                    'quantity' => $row['quantity'],
                    'stock' => round($stock, 2),
                    'share' => round($share, 1),
                    'cumulative' => round($cumulative, 1),
                    // La clase la decide dónde empieza el repuesto en el acumulado: el que
                    // cruza el 80 % todavía es A.
                    'proposed' => match (true) {
                        $row['value'] <= 0 => SparePartAbcClassification::C,
                        $before < self::CLASS_A_LIMIT => SparePartAbcClassification::A,
                        $before < self::CLASS_B_LIMIT => SparePartAbcClassification::B,
                        default => SparePartAbcClassification::C,
                    },
                    'current' => $row['part']->abc_classification,
                    'turns' => $stock > 0 ? round($row['quantity'] / $stock, 1) : null,
                ];
            });
    }

    /** Pone a cada repuesto la clase que le da su consumo. Devuelve cuántos cambiaron. */
    public function applyAbc(Tenant $tenant): int
    {
        $changed = 0;

        foreach ($this->abc($tenant) as $row) {
            if ($row['current'] !== $row['proposed']) {
                SparePart::query()->whereKey($row['id'])->update(['abc_classification' => $row['proposed']->value]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Lo quieto: repuestos con existencia que no han tenido una sola salida en los
     * últimos días, con lo que vale la plata parada.
     *
     * @return Collection<int, array{code: string, name: string, stock: float, value: float, lastOut: ?CarbonImmutable}>
     */
    public function idle(Tenant $tenant, int $days = 180): Collection
    {
        $lastOut = InventoryTransaction::query()->forTenant($tenant->id)
            ->whereIn('type', WarehouseDashboard::OUTGOING_TYPES)
            ->selectRaw('spare_part_id, max(performed_at) as ultima')
            ->groupBy('spare_part_id')
            ->pluck('ultima', 'spare_part_id');

        $since = CarbonImmutable::now()->subDays($days);

        return SparePart::query()->forTenant($tenant->id)->where('is_active', true)
            ->with('warehouseStock')
            ->get()
            ->map(fn (SparePart $part): array => [
                'code' => $part->code,
                'name' => $part->name,
                'stock' => round((float) $part->warehouseStock->sum('current_stock'), 2),
                'value' => round((float) $part->warehouseStock->sum(fn ($row): float => (float) $row->current_stock * (float) $row->average_unit_cost), 0),
                'lastOut' => isset($lastOut[$part->id]) ? CarbonImmutable::parse($lastOut[$part->id]) : null,
            ])
            ->filter(fn (array $row): bool => $row['stock'] > 0 && ($row['lastOut'] === null || $row['lastOut']->lt($since)))
            ->sortByDesc('value')
            ->values();
    }

    /**
     * Lo despachado a órdenes de trabajo, por equipo.
     *
     * @return Collection<int, array{equipment: string, value: float, movements: int}>
     */
    public function byEquipment(Tenant $tenant): Collection
    {
        return InventoryTransaction::query()->forTenant($tenant->id)
            ->where('type', InventoryTransactionType::Consumption->value)
            ->where('performed_at', '>=', CarbonImmutable::now()->subYear())
            ->whereNotNull('work_order_id')
            ->with('workOrder:id,equipment_id', 'workOrder.equipment:id,code,name')
            ->get()
            ->groupBy(fn (InventoryTransaction $movement): string => $movement->workOrder?->equipment ? $movement->workOrder->equipment->code.' · '.$movement->workOrder->equipment->name : 'Sin equipo')
            ->map(fn (Collection $movements, string $equipment): array => [
                'equipment' => $equipment,
                'value' => round((float) $movements->sum(fn (InventoryTransaction $m): float => abs((float) $m->quantity) * (float) $m->unit_cost), 0),
                'movements' => $movements->count(),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * Las salidas sin orden de trabajo, por motivo, y quiénes más reciben.
     *
     * @return array{reasons: Collection<int, array{reason: string, value: float, movements: int}>, recipients: Collection<int, array{name: string, value: float, movements: int}>}
     */
    public function exits(Tenant $tenant): array
    {
        $exits = InventoryTransaction::query()->forTenant($tenant->id)
            ->where('type', InventoryTransactionType::Exit->value)
            ->where('performed_at', '>=', CarbonImmutable::now()->subYear())
            ->get(['exit_reason', 'recipient_name', 'quantity', 'unit_cost']);

        $summarise = fn (Collection $movements): array => [
            'value' => round((float) $movements->sum(fn (InventoryTransaction $m): float => abs((float) $m->quantity) * (float) $m->unit_cost), 0),
            'movements' => $movements->count(),
        ];

        return [
            'reasons' => $exits->groupBy(fn (InventoryTransaction $m): string => $m->exit_reason?->label() ?? 'Sin motivo')
                ->map(fn (Collection $group, string $reason): array => ['reason' => $reason, ...$summarise($group)])
                ->sortByDesc('value')->values(),
            'recipients' => $exits->filter(fn (InventoryTransaction $m): bool => filled($m->recipient_name))
                ->groupBy('recipient_name')
                ->map(fn (Collection $group, string $name): array => ['name' => $name, ...$summarise($group)])
                ->sortByDesc('value')->take(10)->values(),
        ];
    }

    /** @return Builder<InventoryTransaction> */
    private function outgoing(Tenant $tenant): Builder
    {
        return InventoryTransaction::query()->forTenant($tenant->id)
            ->whereIn('type', WarehouseDashboard::OUTGOING_TYPES)
            ->where('performed_at', '>=', CarbonImmutable::now()->subYear());
    }
}
