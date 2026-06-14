<?php

namespace App\Domain\Reports\Excel;

use App\Models\MaintenancePlan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Rap2hpoutre\FastExcel\FastExcel;

class MaintenancePlanExcelExport
{
    public function __construct(private readonly string $tenantId) {}

    public function rows(): LazyCollection
    {
        return MaintenancePlan::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->with(['equipment.plant', 'equipment.area', 'responsibleUser', 'tasks'])
            ->orderBy('plan_number')
            ->lazy();
    }

    public function map(MaintenancePlan $plan): array
    {
        return [
            'N° Plan' => $plan->plan_number,
            'Nombre' => $plan->name,
            'Equipo' => $plan->equipment?->name,
            'Planta' => $plan->equipment?->plant?->name,
            'Área' => $plan->equipment?->area?->name,
            'Responsable' => $plan->responsibleUser?->name,
            'Disparador' => $plan->trigger_source?->label(),
            'Frecuencia' => $plan->frequencyLabel(),
            'Duración Est. (min)' => $plan->estimated_duration_minutes,
            'N° Tareas' => $plan->tasks->count(),
            'Activo' => $plan->is_active ? 'Sí' : 'No',
            'Últ. Generación' => $plan->last_generated_at?->format('Y-m-d H:i'),
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
