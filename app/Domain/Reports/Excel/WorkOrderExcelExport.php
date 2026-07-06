<?php

namespace App\Domain\Reports\Excel;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Rap2hpoutre\FastExcel\FastExcel;

class WorkOrderExcelExport
{
    public function __construct(private readonly string $tenantId) {}

    public function rows(): LazyCollection
    {
        return WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->with(['equipment', 'plant', 'area', 'createdBy', 'assignedSupervisor'])
            ->orderBy('created_at', 'desc')
            ->lazy();
    }

    public function map(WorkOrder $workOrder): array
    {
        return [
            'N° OT' => $workOrder->work_order_number,
            'Título' => $workOrder->title,
            'Tipo' => $workOrder->work_order_type?->label(),
            'Estado' => $workOrder->status?->label(),
            'Prioridad' => $workOrder->priority?->label(),
            'Equipo' => $workOrder->equipment?->name,
            'Planta' => $workOrder->plant?->name,
            'Área' => $workOrder->area?->name,
            'Creado Por' => $workOrder->createdBy?->name,
            'Supervisor' => $workOrder->assignedSupervisor?->name,
            'Equipo Detenido' => $workOrder->equipment_stopped ? 'Sí' : 'No',
            'Tiempo Parada (min)' => $workOrder->downtime_minutes,
            'Inicio Planif.' => $workOrder->planned_start_at?->format('Y-m-d H:i'),
            'Fin Planif.' => $workOrder->planned_end_at?->format('Y-m-d H:i'),
            'Inicio Real' => $workOrder->actual_start_at?->format('Y-m-d H:i'),
            'Fin Real' => $workOrder->actual_end_at?->format('Y-m-d H:i'),
            'Horas MO Reales' => $workOrder->actualHours(),
            'Costo Total' => $workOrder->actual_cost_total !== null ? round((float) $workOrder->actual_cost_total, 2) : null,
            'Moneda' => $workOrder->currency_code,
            'Fecha Creación' => $workOrder->created_at?->format('Y-m-d H:i'),
            'Fecha Cierre' => $workOrder->closed_at?->format('Y-m-d H:i'),
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
