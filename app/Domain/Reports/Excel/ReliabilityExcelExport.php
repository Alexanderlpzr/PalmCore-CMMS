<?php

namespace App\Domain\Reports\Excel;

use App\Models\EquipmentKpi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Rap2hpoutre\FastExcel\FastExcel;

class ReliabilityExcelExport
{
    public function __construct(private readonly string $tenantId) {}

    public function rows(): LazyCollection
    {
        return EquipmentKpi::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->with(['equipment.plant', 'equipment.area'])
            ->orderBy('period_start', 'desc')
            ->lazy();
    }

    public function map(EquipmentKpi $kpi): array
    {
        return [
            'Equipo' => $kpi->equipment?->name,
            'Planta' => $kpi->equipment?->plant?->name,
            'Área' => $kpi->equipment?->area?->name,
            'Período (inicio)' => $kpi->period_start?->format('Y-m-d'),
            'Período (fin)' => $kpi->period_end?->format('Y-m-d'),
            'MTBF (h)' => $kpi->mtbf_hours !== null ? round((float) $kpi->mtbf_hours, 2) : null,
            'MTTR (h)' => $kpi->mttr_hours !== null ? round((float) $kpi->mttr_hours, 2) : null,
            'Disponibilidad (%)' => $kpi->availability_percentage !== null ? round((float) $kpi->availability_percentage, 2) : null,
            'Disponib. No Planif. (%)' => $kpi->unplanned_availability_percentage !== null ? round((float) $kpi->unplanned_availability_percentage, 2) : null,
            'Fallas' => $kpi->failure_count,
            'Horas Parada' => $kpi->downtime_hours !== null ? round((float) $kpi->downtime_hours, 2) : null,
            'Últ. Falla' => $kpi->last_failure_at?->format('Y-m-d H:i'),
            'Calculado' => $kpi->last_calculated_at?->format('Y-m-d H:i'),
            'Vencido' => $kpi->is_stale ? 'Sí' : 'No',
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
