<?php

namespace App\Domain\Reports\Excel;

use App\Domain\Analytics\Services\MaintenanceCostReportService;
use App\Models\WorkOrder;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El reporte de gasto del mes en Excel, para llevarlo a la reunión de
 * presupuesto. Una fila por OT completada con su desglose de costo; síncrono
 * porque un mes son unas pocas cientas de órdenes, no el histórico entero.
 */
class MonthlyMaintenanceCostExcelExport
{
    public function __construct(private readonly MaintenanceCostReportService $service) {}

    public function download(string $tenantId, string $plantId, int $year, int $month): BinaryFileResponse|StreamedResponse
    {
        $workOrders = $this->service->completedWorkOrders($tenantId, $plantId, $year, $month);

        $rows = $workOrders->map(fn (WorkOrder $wo): array => [
            'N° OT' => $wo->work_order_number,
            'Título' => $wo->title,
            'Tipo' => $wo->work_order_type?->label(),
            'Equipo' => $wo->equipment?->name,
            'Área' => $wo->area?->name,
            'Completada' => $wo->completed_at?->format('Y-m-d'),
            'Mano de Obra' => self::amount($wo->actual_cost_labor),
            'Repuestos' => self::amount($wo->actual_cost_parts),
            'Terceros' => self::amount($wo->actual_cost_external),
            'Total' => self::amount($wo->actual_cost_total),
        ]);

        $filename = sprintf('gastos-mantenimiento-%04d-%02d.xlsx', $year, $month);

        return (new FastExcel($rows))->download($filename);
    }

    private static function amount(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
