<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Models\Employee;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrderPart;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Las cifras del tablero de Almacén: lo que hay que reponer, lo que está por llegar, lo
 * que vale el inventario y en qué se va.
 *
 * Igual que el de Talento Humano, no guarda nada: lee existencias, movimientos y órdenes
 * de compra, y cada número lleva a la lista donde se resuelve.
 */
class WarehouseDashboard
{
    /** Las salidas de inventario: lo que se fue del almacén, por la vía que sea. */
    public const OUTGOING_TYPES = [InventoryTransactionType::Exit, InventoryTransactionType::Consumption];

    /**
     * Lo que requiere atención hoy.
     *
     * @return array{activeParts: int, belowMinimum: int, outOfStock: int, openPurchaseOrders: int, overduePurchaseOrders: int, inventoryValue: float, movementsToday: int, pendingDispatches: int}
     */
    public function attention(Tenant $tenant): array
    {
        $today = CarbonImmutable::now($tenant->plantTimezone());

        return [
            'activeParts' => SparePart::query()->forTenant($tenant->id)->where('is_active', true)->count(),
            'belowMinimum' => self::belowMinimum(SparePart::query()->forTenant($tenant->id))->count(),
            'outOfStock' => self::outOfStock(SparePart::query()->forTenant($tenant->id))->count(),
            'openPurchaseOrders' => PurchaseOrder::query()->forTenant($tenant->id)->open()->count(),
            'overduePurchaseOrders' => PurchaseOrder::query()
                ->forTenant($tenant->id)
                ->open()
                ->whereNotNull('expected_at')
                ->whereDate('expected_at', '<', $today->toDateString())
                ->count(),
            'inventoryValue' => round((float) WarehouseSparePart::query()
                ->forTenant($tenant->id)
                ->selectRaw('coalesce(sum(current_stock * average_unit_cost), 0) as valor')
                ->value('valor'), 0),
            'movementsToday' => InventoryTransaction::query()
                ->forTenant($tenant->id)
                ->whereBetween('performed_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])
                ->count(),
            'pendingDispatches' => self::pendingDispatches(WorkOrderPart::query()->forTenant($tenant->id))->count(),
        ];
    }

    /**
     * Lo que pidieron las órdenes de trabajo y el almacén todavía no ha entregado. Incluye
     * las OT ya cerradas: se cierran a menudo con el repuesto puesto, y el almacén
     * confirma después lo que entregó.
     *
     * @param  Builder<WorkOrderPart>  $query
     * @return Builder<WorkOrderPart>
     */
    public static function pendingDispatches(Builder $query): Builder
    {
        return $query
            ->whereNotNull('spare_part_id')
            ->whereNotNull('warehouse_id')
            ->whereIn('status', [WorkOrderPartStatus::Requested->value, WorkOrderPartStatus::Reserved->value])
            ->whereHas('workOrder', fn (Builder $order) => $order->where('status', '!=', WorkOrderStatus::Cancelled->value));
    }

    /**
     * Los nombres que se le ofrecen al almacenista en «quién recibe»: el personal activo
     * y quienes ya han recibido algo. Se puede escribir cualquier otro.
     *
     * @return list<string>
     */
    public function recipientSuggestions(Tenant $tenant): array
    {
        $employees = Employee::query()->forTenant($tenant->id)->active()
            ->orderBy('first_name')->get(['first_name', 'last_name'])
            ->map(fn (Employee $employee): string => $employee->fullName());

        $previous = InventoryTransaction::query()->forTenant($tenant->id)
            ->whereNotNull('recipient_name')->distinct()->pluck('recipient_name');

        return $employees->merge($previous)->filter()->unique()->sort()->values()->all();
    }

    /**
     * Valor de lo que salió del almacén cada mes —salidas y consumo de órdenes—, los
     * últimos seis meses, el más viejo primero.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function monthlyOutgoingValue(Tenant $tenant, int $months = 6): array
    {
        $now = CarbonImmutable::now($tenant->plantTimezone())->startOfMonth();
        $labels = [];
        $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $now->subMonthsNoOverflow($i);
            $labels[] = ucfirst($month->locale('es')->translatedFormat('M Y'));
            $values[] = round(abs((float) InventoryTransaction::query()
                ->forTenant($tenant->id)
                ->whereIn('type', self::OUTGOING_TYPES)
                ->whereBetween('performed_at', [$month->utc(), $month->endOfMonth()->utc()])
                ->sum('total_cost')), 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Los repuestos que más salieron en los últimos días, por cantidad.
     *
     * @return array<string, float> nombre => cantidad
     */
    public function mostUsed(Tenant $tenant, int $days = 90, int $limit = 8): array
    {
        return InventoryTransaction::query()
            ->forTenant($tenant->id)
            ->whereIn('type', self::OUTGOING_TYPES)
            ->where('performed_at', '>=', CarbonImmutable::now()->subDays($days))
            ->selectRaw('spare_part_name_snapshot as nombre, sum(abs(quantity)) as cantidad')
            ->groupBy('spare_part_name_snapshot')
            ->orderByDesc('cantidad')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->nombre => round((float) $row->cantidad, 2)])
            ->all();
    }

    /**
     * Los últimos movimientos, para ver de un vistazo qué entró y qué salió.
     *
     * @return Collection<int, InventoryTransaction>
     */
    public function recentMovements(Tenant $tenant, int $limit = 8): Collection
    {
        return InventoryTransaction::query()
            ->forTenant($tenant->id)
            ->with(['warehouse:id,name'])
            ->latest('performed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Repuestos activos con mínimo definido cuya existencia total, sumando todos los
     * almacenes, está en el mínimo o por debajo.
     *
     * @param  Builder<SparePart>  $query
     * @return Builder<SparePart>
     */
    public static function belowMinimum(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('minimum_stock', '>', 0)
            ->whereRaw('(select coalesce(sum(current_stock), 0) from warehouse_spare_parts where warehouse_spare_parts.spare_part_id = spare_parts.id) <= spare_parts.minimum_stock');
    }

    /**
     * Repuestos activos sin una sola unidad en ningún almacén.
     *
     * @param  Builder<SparePart>  $query
     * @return Builder<SparePart>
     */
    public static function outOfStock(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereRaw('(select coalesce(sum(current_stock), 0) from warehouse_spare_parts where warehouse_spare_parts.spare_part_id = spare_parts.id) <= 0');
    }

    /** @return list<PurchaseOrderStatus> */
    public static function openStatuses(): array
    {
        return [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived];
    }
}
