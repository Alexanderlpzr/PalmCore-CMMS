<?php

namespace App\Domain\Reports\Excel;

use App\Models\SparePart;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Rap2hpoutre\FastExcel\FastExcel;

class InventoryExcelExport
{
    public function __construct(private readonly string $tenantId) {}

    public function rows(): LazyCollection
    {
        return SparePart::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->with(['manufacturer', 'supplier', 'warehouseStock.warehouse'])
            ->orderBy('code')
            ->lazy();
    }

    public function map(SparePart $part): array
    {
        $totalStock = $part->warehouseStock->sum('current_stock');
        $avgCost = $part->warehouseStock->avg('average_unit_cost');
        $totalValue = $part->warehouseStock->sum(fn ($ws) => $ws->current_stock * ($ws->average_unit_cost ?? 0));

        return [
            'Código' => $part->code,
            'Nombre' => $part->name,
            'Categoría' => $part->category_type?->label(),
            'Fabricante' => $part->manufacturer?->name,
            'Proveedor' => $part->supplier?->name,
            'Unidad' => $part->unit?->value,
            'Stock Total' => $totalStock,
            'Stock Mín.' => $part->minimum_stock,
            'Punto de Reorden' => $part->reorder_point,
            'Costo Unitario' => $part->unit_cost !== null ? round((float) $part->unit_cost, 2) : null,
            'Costo Prom.' => $avgCost !== null ? round((float) $avgCost, 2) : null,
            'Valor Total' => $totalValue > 0 ? round((float) $totalValue, 2) : 0,
            'Estado' => $part->is_active ? 'Activo' : 'Inactivo',
        ];
    }

    public function store(string $path): void
    {
        $disk = Storage::disk('reports');
        $disk->makeDirectory(dirname($path));

        (new FastExcel($this->generate()))
            ->export($disk->path($path));
    }

    private function generate(): \Generator
    {
        foreach ($this->rows() as $row) {
            yield $this->map($row);
        }
    }
}
